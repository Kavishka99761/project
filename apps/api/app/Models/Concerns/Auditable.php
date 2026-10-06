<?php

namespace App\Models\Concerns;

use App\Support\Activity\ActivityContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Feeds create/update/delete diffs of a model into the current request's
 * activity record, so the audit trail stores *what* changed (old → new),
 * not just which endpoint was called.
 *
 * Large or noisy attributes are excluded via $auditExclude on the model.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => self::pushAuditChange($model, 'created'));
        static::updated(fn (Model $model) => self::pushAuditChange($model, 'updated'));
        static::deleted(fn (Model $model) => self::pushAuditChange($model, 'deleted'));
    }

    private static function pushAuditChange(Model $model, string $event): void
    {
        $context = app(ActivityContext::class);
        if (! $context->isRecording()) {
            return;
        }

        $exclude = array_merge(
            ['created_at', 'updated_at', 'password', 'remember_token'],
            property_exists($model, 'auditExclude') ? $model->auditExclude : [],
        );

        $changes = [];
        if ($event === 'updated') {
            foreach ($model->getChanges() as $key => $new) {
                if (in_array($key, $exclude, true)) {
                    continue;
                }
                $changes[$key] = ['old' => self::auditValue($model->getOriginal($key)), 'new' => self::auditValue($new)];
            }
            if ($changes === []) {
                return;
            }
        } elseif ($event === 'created') {
            foreach ($model->getAttributes() as $key => $value) {
                if (! in_array($key, $exclude, true)) {
                    $changes[$key] = self::auditValue($value);
                }
            }
        }

        $context->recordChange(class_basename($model), $model->getKey(), $event, $changes);
    }

    private static function auditValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if (is_string($value) && mb_strlen($value) > 200) {
            return mb_substr($value, 0, 200).'…';
        }
        if (is_array($value)) {
            return '[array]';
        }

        return $value;
    }
}
