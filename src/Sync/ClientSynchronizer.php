<?php

namespace IlBronza\Tasks\Sync;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ClientSynchronizer
{
    private function connection(string $installation): PendingRequest
    {
        $url = config('tasks.sync.hub_url');
        $token = config('tasks.sync.token');
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || !is_string($token) || $token === '')
            throw new RuntimeException('Configure an HTTPS TASKS_SYNC_HUB_URL and TASKS_SYNC_TOKEN.');

        return Http::baseUrl(rtrim($url, '/') . '/api/tasks-sync/v1')->acceptJson()
            ->withToken($token)->withHeaders(['X-Tasks-Installation' => $installation])
            ->withoutRedirecting()->connectTimeout(5)->timeout(20);
    }

    public function run(): array
    {
        if (!TaskSync::client())
            throw new RuntimeException('Run tasks:sync on a client installation.');

        $installation = TaskSync::installationId();
        $http = $this->connection($installation);
        $sent = 0;
        $errors = [];
        $outbox = DB::table('tasks__sync_outbox')->where('installation_id', $installation);
        $last = (clone $outbox)->max('id');
        if ($last !== null) {
            $outbox->where('id', '<=', $last)->chunkById(100, function ($events) use ($http, &$sent, &$errors) {
                foreach ($events as $event) {
                    if (isset($errors[$event->task_id]))
                        continue;
                    $path = 'tasks/' . $event->task_id;
                    if ($event->type === 'message')
                        $path .= '/messages/' . $event->event_id;
                    $response = $http->put($path, json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR));
                    if (!$response->successful() || $response->json('accepted') !== true
                        || $response->json('installation') !== $event->installation_id
                        || $response->json('task_id') !== $event->task_id
                        || $response->json('event_id') !== $event->event_id) {
                        $errors[$event->task_id] = "Event {$event->id}: unexpected reply (HTTP {$response->status()}).";
                        continue;
                    }
                    DB::table('tasks__sync_outbox')->where('id', $event->id)->delete();
                    $sent++;
                }
            });
        }

        $received = $this->pull($http, $installation);
        return [
            'sent' => $sent,
            'received' => $received,
            'errors' => $errors,
        ];
    }

    private function pull(PendingRequest $http, string $installation): int
    {
        DB::table('tasks__sync_cursors')->insertOrIgnore(['installation_id' => $installation]);
        $cursor = DB::table('tasks__sync_cursors')->where('installation_id', $installation);
        $received = 0;
        do {
            $after = (int) $cursor->value('revision');
            $response = $http->get('changes', ['after' => $after]);
            $changes = $response->json('changes');
            if (!$response->successful() || $response->json('installation') !== $installation || !is_array($changes))
                throw new RuntimeException("Unexpected hub feed (HTTP {$response->status()}).");
            if (!$changes)
                break;

            $received += DB::transaction(function () use ($cursor, $after, $changes, $installation) {
                if ((int) (clone $cursor)->lockForUpdate()->firstOrFail()->revision !== $after)
                    return 0;

                $revision = $after;
                foreach ($changes as $change) {
                    if (($change['revision'] ?? null) !== ++$revision)
                        throw new RuntimeException('Non-consecutive hub revisions.');
                    $task = config('tasks.models.task.class')::withTrashed()
                        ->where('sync_installation_id', $installation)->lockForUpdate()->findOrFail($change['task_id']);
                    $this->apply($task, $change);
                }
                $cursor->update(['revision' => $revision]);
                return count($changes);
            }, 3);
        } while (count($changes) >= 100);

        return $received;
    }

    private function apply($task, array $change): void
    {
        $data = $change['payload'];
        if ($change['type'] === 'message') {
            Validator::make($data, [
                'id' => 'required|uuid',
                'body' => 'required|string|max:10240',
                'author' => 'required|string|max:255',
                'created_at' => 'required|date',
            ])->validate();
            TaskSync::importMessage($task, $data);
            return;
        }
        if ($change['type'] !== 'state')
            throw new RuntimeException('Unknown hub event type.');

        Validator::make($data, [
            'title' => 'present|nullable|string|max:255',
            'status' => ['required', \Illuminate\Validation\Rule::in(array_keys($task::getWorkflowStatuses()))],
            'end_date' => 'present|nullable|date',
            'deleted_at' => 'present|nullable|date',
            'assignee' => 'present|nullable|string|max:255',
            'assignee_key' => 'present|nullable|uuid',
        ])->validate();

        // The opaque remote identity supports filtering without linking to a local user.
        $task->newQueryWithoutScopes()->whereKey($task->getKey())->update([
            'title' => $data['title'],
            'status' => $data['status'],
            'end_date' => $this->localDate($data['end_date']),
            'deleted_at' => $this->localDate($data['deleted_at']),
            'assignee_user_id' => null,
            'sync_assignee' => $data['assignee'],
            'sync_assignee_key' => $data['assignee_key'],
        ]);
    }

    private function localDate(?string $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
