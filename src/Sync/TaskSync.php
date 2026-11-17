<?php

namespace IlBronza\Tasks\Sync;

use IlBronza\Tasks\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Ramsey\Uuid\Uuid;

class TaskSync
{
    public static function client(): bool
    {
        return config('tasks.sync.mode') === 'client';
    }

    public static function hub(): bool
    {
        return config('tasks.sync.mode') === 'hub';
    }

    public static function installationId(): string
    {
        $id = config('tasks.sync.installation');
        if (!Str::isUuid($id))
            throw new LogicException('Configure TASKS_SYNC_INSTALLATION with the UUID issued by the hub.');

        return $id;
    }

    public static function scopedId(string $namespace, string $kind, string $id): string
    {
        return Uuid::uuid5($namespace, $kind . '/' . strtolower($id))->toString();
    }

    public static function enqueueOpening(Task $task): void
    {
        static::enqueue($task, 'opened', $task->getKey(), [
            'project_id' => $task->project_id,
            'project_name' => $task->project()->firstOrFail()->name,
            'title' => $task->title ?: __('tasks::tasks.untitled'),
            'description' => $task->description,
            'link' => $task->link,
            'requester' => $task->sync_requester,
            'created_at' => $task->created_at->toISOString(),
        ]);
    }

    private static function enqueue(Task $task, string $type, string $eventId, array $payload): void
    {
        $task->getConnection()->table('tasks__sync_outbox')->insert([
            'installation_id' => $task->sync_installation_id,
            'task_id' => $task->getKey(),
            'event_id' => $eventId,
            'type' => $type,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    // Called inside the same transaction as the task or message write. Serializing
    // revision allocation per installation makes a committed cursor safe to resume.
    private static function publish(Task $task, string $type, array $payload): void
    {
        $connection = $task->getConnection();
        $peer = $connection->table('tasks__sync_installations')->where('id', $task->sync_installation_id);
        $revision = (int) $peer->lockForUpdate()->firstOrFail()->revision + 1;
        $peer->update(['revision' => $revision]);
        $connection->table('tasks__sync_changes')->insert([
            'installation_id' => $task->sync_installation_id,
            'revision' => $revision,
            'task_id' => $task->sync_origin_id,
            'type' => $type,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    public static function publishState(Task $task): void
    {
        static::publish($task, 'state', [
            'title' => $task->title,
            'status' => $task->status ?: 'open',
            'end_date' => $task->end_date?->toISOString(),
            'assignee' => $task->assignee?->name,
            'assignee_key' => $task->assignee
                ? static::scopedId($task->sync_installation_id, 'assignee', (string) $task->assignee->getKey()) : null,
            'deleted_at' => $task->deleted_at?->toISOString(),
        ]);
    }

    public static function sharedMessage(Task $task, $note, string $author): void
    {
        $payload = [
            'id' => $note->getKey(),
            'body' => $note->notes,
            'author' => $author,
            'created_at' => $note->created_at->toISOString(),
        ];
        if (static::client())
            static::enqueue($task, 'message', $note->getKey(), $payload);
        elseif (static::hub())
            static::publish($task, 'message', $payload);
    }

    public static function importMessage(Task $task, array $payload): void
    {
        $id = static::scopedId($task->getKey(), 'message', $payload['id']);
        if ($task->notes()->withArchived()->withTrashed()->whereKey($id)->exists())
            return;

        $note = $task->notes()->make();
        $note->id = $id;
        $note->notes = $payload['body'];
        $note->task_sync_author = $payload['author'];
        $note->conversation_visibility = 'shared';
        $note->conversation_order = $task->nextConversationOrder();
        $note->created_at = Carbon::parse($payload['created_at'])->setTimezone(config('app.timezone'));
        // The UUID and author already came from the remote installation. In
        // particular the local Notes author observer must not claim this message.
        $note->incrementing = false;
        $note->saveQuietly();
    }
}
