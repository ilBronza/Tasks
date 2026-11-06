<?php

namespace IlBronza\Tasks\Models;

use Illuminate\Database\Eloquent\Model;

class TaskAssignment extends Model
{
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function getTable()
    {
        return config('tasks.assignments.table');
    }

    public function task()
    {
        return $this->belongsTo(config('tasks.models.task.class'), 'task_id');
    }

    public function toConversationMessage($user): array
    {
        return [
            'id' => 'assignment-' . $this->getKey(),
            'body' => $this->comment ?? '',
            'author' => $this->actor_name ?? __('tasks::tasks.systemActor'),
            'isOwn' => $user && (string) $this->actor_user_id === (string) $user->getAuthIdentifier(),
            'createdAt' => $this->created_at->toISOString(),
            'dateLabel' => $this->created_at->format('d/m/Y H:i'),
            'conversationOrder' => (int) $this->conversation_order,
            'visibility' => 'internal',
            'attachments' => [],
            'assignment' => [
                'fromId' => $this->from_user_id === null ? null : (string) $this->from_user_id,
                'from' => $this->from_name ?? __('tasks::tasks.unassigned'),
                'toId' => $this->to_user_id === null ? null : (string) $this->to_user_id,
                'to' => $this->to_name ?? __('tasks::tasks.unassigned'),
            ],
        ];
    }
}
