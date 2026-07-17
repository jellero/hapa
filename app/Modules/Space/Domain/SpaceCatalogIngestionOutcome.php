<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Domain;

enum SpaceCatalogIngestionOutcome: string
{
    case CreatedPendingReview = 'created_pending_review';
    case LinkedExisting = 'linked_existing';
    case Updated = 'updated';
    case Duplicate = 'duplicate';
    case IgnoredStale = 'ignored_stale';
    case IdentityConflict = 'identity_conflict';
}
