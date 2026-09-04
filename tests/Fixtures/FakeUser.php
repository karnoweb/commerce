<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in for the host's user model in standalone package tests. Never
 * queried directly — only its class name is stored as a soft/morph
 * reference (commerce.models.user, wallets.reference_type).
 */
final class FakeUser extends Model
{
    protected $table = 'fake_users';

    protected $guarded = [];
}
