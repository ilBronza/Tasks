<?php

namespace IlBronza\Tasks\Models\Traits;

use IlBronza\Tasks\Models\TaskAssignment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait TracksTaskAssignments
{
    protected ?string $assignmentComment = null;

    public function assignments()
    {
        return $this->hasMany(TaskAssignment::class, 'task_id');
    }

    // Call while holding the task row lock; messages and assignments share a sequence.
    public function nextConversationOrder(): int
    {
        return max(
            (int) $this->notes()->withArchived()->withTrashed()->max('conversation_order'),
            (int) $this->assignments()->max('conversation_order')
        ) + 1;
    }

    // A transient form field: the comment belongs to the history row, not the task.
    public function setAssignmentComment(?string $comment): void
    {
        $comment = trim($comment ?? '');
        $this->assignmentComment = $comment === '' ? null : $comment;
    }

    public function setAssignmentCommentAttribute($comment): void
    {
        $this->setAssignmentComment($comment);
    }

    public function getAssignmentCommentAttribute(): ?string
    {
        return $this->assignmentComment;
    }

    public function save(array $options = [])
    {
        try {
            if (!$this->isDirty('assignee_user_id') && $this->assignmentComment === null)
                return parent::save($options);

            // Covers regular Eloquent/CRUD saves too. Lock the stored task so stale
            // model instances record the actual previous assignee, and roll back
            // both writes if either the task or its history cannot be saved.
            return $this->getConnection()->transaction(function () use ($options) {
                $previous = $this->exists
                    ? $this->newQueryWithoutScopes()->lockForUpdate()->findOrFail($this->getKey())->assignee_user_id
                    : null;
                $recipientId = $this->assignee_user_id;
                $changed = (string) $previous !== (string) $recipientId;

                Validator::make(['comment' => $this->assignmentComment], [
                    'comment' => ['nullable', 'string', 'max:10240'],
                ])->validate();

                if (!$changed && $this->assignmentComment !== null)
                    throw ValidationException::withMessages(['assignment_comment' => __('tasks::tasks.assignmentUnchanged')]);

                $recipient = $changed && $recipientId !== null
                    ? $this->getPossibleAssignees()->first(fn ($user) => (string) $user->getKey() === (string) $recipientId)
                    : null;
                if ($changed && $recipientId !== null && !$recipient)
                    throw ValidationException::withMessages(['assignee_user_id' => __('tasks::tasks.invalidAssignee')]);

                $actor = auth()->user();
                $previousUser = $changed && $previous !== null
                    ? config('auth.providers.users.model')::find($previous) : null;
                $saved = parent::save($options);

                if ($saved && $changed) {
                    $entry = $this->assignments()->make();
                    $entry->actor_user_id = $actor?->getAuthIdentifier();
                    $entry->actor_name = $actor?->name;
                    $entry->from_user_id = $previous;
                    $entry->from_name = $previousUser?->name ?? ($previous === null ? null : __('notes::notes.unknownUser'));
                    $entry->to_user_id = $recipientId;
                    $entry->to_name = $recipient?->name;
                    $entry->comment = $this->assignmentComment;
                    $entry->conversation_order = $this->nextConversationOrder();
                    $entry->created_at = now();
                    if (!$entry->saveOrFail())
                        throw new \RuntimeException('Unable to save the task assignment history.');
                    $this->unsetRelation('assignee');
                    $this->unsetRelation('assignments');
                }

                return $saved;
            });
        } finally {
            $this->assignmentComment = null;
        }
    }
}
