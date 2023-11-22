<?php

namespace markhuot\craftpest\behaviors;

use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\elements\ElementCollection;
use Illuminate\Support\Collection;
use yii\base\Behavior;

/**
 * @property ElementInterface $owner
 */
class SnapshotableBehavior extends Behavior
{
    /**
     * @param array $extraAttributes Any additional fields that should be included in the snapshot
     * @param array $attributes The default list of attributes that should be included in a snapshot
     */
    public function toSnapshot(array $extraAttributes=[], array $attributes=['title', 'slug', 'isDraft', 'isRevision', 'isNewForSite', 'isUnpublishedDraft', 'enabled', 'archived', 'uri', 'trashed', 'ref', 'status', 'url'])
    {
        $customFields = collect($this->owner->getFieldLayout()->getCustomFields())
            ->mapWithKeys(function ($field) {
                return [$field->handle => $field];
            })

            // remove any ElementQueries from the element so we don't try to snapshot
            // a serialized query. It will never match because it may have a dynamic `->where()`
            // or an `->ownerId` that changes with each generated element.
            ->filter(fn ($field, $handle) => ! ($this->owner->{$handle} instanceof ElementQuery))

            // snapshot any eager loaded element queries so nested elements are downcasted
            // to a reproducible array
            ->map(function ($value, $handle) {
                if ($this->owner->{$handle} instanceof ElementCollection) {
                    $value = $this->owner->{$handle};
                    return $value->map->toSnapshot(); // @phpstan-ignore-line can't get PHPStan to reason about the ->map higher order callable
                }

                return $value;
            });

        return $customFields->merge(
            collect($attributes)->merge($extraAttributes)
                ->mapWithKeys(fn ($attribute) => [
                    $attribute => $this->owner->{$attribute} ?? null,
                ])
            )
            ->all();
    }
}
