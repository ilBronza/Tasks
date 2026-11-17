<?php

namespace IlBronza\Tasks\Http\Controllers;

use IlBronza\Tasks\Sync\TaskSync;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskSyncController
{
    private function installation(Request $request): object
    {
        abort_unless(TaskSync::hub(), 404);
        $id = $request->header('X-Tasks-Installation');
        $token = $request->bearerToken();
        abort_unless(Str::isUuid($id) && is_string($token) && $token !== '', 401);
        $peer = DB::table('tasks__sync_installations')->where('id', $id)->first();
        abort_unless($peer && hash_equals($peer->token_hash, hash('sha256', $token)), 401);

        return $peer;
    }

    public function open(Request $request, string $origin)
    {
        $peer = $this->installation($request);
        $data = $request->validate([
            'project_id' => 'required|uuid',
            'project_name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'link' => 'nullable|string|url:http,https|max:2048',
            'requester' => 'nullable|string|max:255',
            'created_at' => 'required|date',
        ]);
        DB::transaction(function () use ($peer, $origin, $data) {
            DB::table('tasks__sync_installations')->where('id', $peer->id)->lockForUpdate()->firstOrFail();
            $class = config('tasks.models.task.class');
            if ($class::withTrashed()->where('sync_installation_id', $peer->id)->where('sync_origin_id', $origin)->exists())
                return;

            $task = new $class;
            $task->sync_installation_id = $peer->id;
            $task->sync_origin_id = $origin;
            $task->sync_requester = $data['requester'] ?? null;
            $task->project_id = $this->projectId($peer, $data);
            $task->title = $data['title'];
            $task->description = $data['description'] ?? null;
            $task->link = $data['link'] ?? null;
            $task->created_at = Carbon::parse($data['created_at'])->setTimezone(config('app.timezone'));
            $task->status = 'open';
            $task->saveOrFail();
        }, 3);

        return $this->receipt($peer, $origin, $origin);
    }

    private function projectId(object $peer, array $data): string
    {
        $id = TaskSync::scopedId($peer->id, 'project', $data['project_id']);
        $table = DB::table(config('tasks.models.project.table'));
        if ((clone $table)->where('id', $id)->exists())
            return $id;

        $name = $peer->name === $data['project_name'] ? $peer->name : $peer->name . ' · ' . $data['project_name'];
        $row = [
            'id' => $id,
            'name' => Str::limit($name, 250, ''),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (!$table->insertOrIgnore($row)) {
            $row['name'] = Str::limit($name, 210, '') . ' (' . $id . ')';
            $table->insert($row);
        }

        return $id;
    }

    public function message(Request $request, string $origin, string $message)
    {
        $peer = $this->installation($request);
        $data = $request->validate([
            'body' => 'required|string|max:10240',
            'author' => 'required|string|max:255',
            'created_at' => 'required|date',
        ]);
        $data['id'] = $message;
        DB::transaction(function () use ($peer, $origin, $data) {
            $task = config('tasks.models.task.class')::withTrashed()
                ->where('sync_installation_id', $peer->id)->where('sync_origin_id', $origin)
                ->lockForUpdate()->firstOrFail();
            TaskSync::importMessage($task, $data);
        }, 3);

        return $this->receipt($peer, $origin, $message);
    }

    private function receipt(object $peer, string $task, string $event)
    {
        return response()->json([
            'accepted' => true,
            'installation' => $peer->id,
            'task_id' => $task,
            'event_id' => $event,
        ]);
    }

    public function changes(Request $request)
    {
        $peer = $this->installation($request);
        $data = $request->validate(['after' => 'required|integer|min:0']);
        abort_if((int) $data['after'] > (int) $peer->revision, 409, 'Cursor ahead of hub revision.');
        $events = DB::table('tasks__sync_changes')->where('installation_id', $peer->id)
            ->where('revision', '>', $data['after'])->orderBy('revision')->limit(100)->get()
            ->map(fn ($row) => [
                'revision' => (int) $row->revision,
                'task_id' => $row->task_id,
                'type' => $row->type,
                'payload' => json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR),
            ]);

        return response()->json([
            'installation' => $peer->id,
            'changes' => $events,
        ]);
    }
}
