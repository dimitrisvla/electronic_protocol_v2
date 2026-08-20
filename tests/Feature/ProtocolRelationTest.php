<?php

use App\Enums\UserRole;
use App\Models\Protocol;
use App\Models\ProtocolRelation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function createRelationFoundationProtocol(
    User $creator,
    int $number,
    int $year = 2026
): Protocol {
    return Protocol::create([
        'protocol_number' => $number,
        'protocol_year' => $year,
        'protocol_date' => '2026-08-20',
        'direction' => 'incoming',
        'subject' => "Πρωτόκολλο σχέσης {$number}/{$year}",
        'sender' => 'Δοκιμαστικός αποστολέας',
        'recipient' => null,
        'notes' => null,
        'archive_folder_id' => null,
        'created_by' => $creator->id,
    ]);
}

test('protocol relations table has the normalized foundation columns', function () {
    expect(Schema::hasTable('protocol_relations'))->toBeTrue()
        ->and(Schema::hasColumns('protocol_relations', [
            'id',
            'first_protocol_id',
            'second_protocol_id',
            'created_by',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('connecting protocols stores one canonical unordered pair', function () {
    $user = User::factory()->create([
        'role' => UserRole::ProtocolOfficer,
    ]);
    $first = createRelationFoundationProtocol($user, 101);
    $second = createRelationFoundationProtocol($user, 102);

    $relation = ProtocolRelation::connect($second, $first, $user);
    $sameRelation = ProtocolRelation::connect($first, $second, $user);

    expect($relation->first_protocol_id)->toBe(min($first->id, $second->id))
        ->and($relation->second_protocol_id)->toBe(max($first->id, $second->id))
        ->and($relation->created_by)->toBe($user->id)
        ->and($sameRelation->is($relation))->toBeTrue()
        ->and(ProtocolRelation::count())->toBe(1);
});

test('eloquent writes normalize reverse pairs before uniqueness is enforced', function () {
    $user = User::factory()->create();
    $first = createRelationFoundationProtocol($user, 111);
    $second = createRelationFoundationProtocol($user, 112);

    ProtocolRelation::create([
        'first_protocol_id' => $second->id,
        'second_protocol_id' => $first->id,
        'created_by' => $user->id,
    ]);

    expect(fn () => ProtocolRelation::create([
        'first_protocol_id' => $first->id,
        'second_protocol_id' => $second->id,
        'created_by' => $user->id,
    ]))->toThrow(QueryException::class);
});

test('a protocol cannot be related to itself or an unsaved protocol', function () {
    $user = User::factory()->create();
    $protocol = createRelationFoundationProtocol($user, 121);

    expect(fn () => ProtocolRelation::connect($protocol, $protocol, $user))
        ->toThrow(\InvalidArgumentException::class);

    expect(fn () => ProtocolRelation::connect(
        $protocol,
        new Protocol(),
        $user
    ))->toThrow(\InvalidArgumentException::class);
});

test('related protocols are available symmetrically from either endpoint', function () {
    $user = User::factory()->create();
    $first = createRelationFoundationProtocol($user, 131);
    $second = createRelationFoundationProtocol($user, 132);
    $third = createRelationFoundationProtocol($user, 133, 2025);

    $firstSecond = ProtocolRelation::connect($first, $second, $user);
    ProtocolRelation::connect($third, $first, $user);

    expect($first->relationsAsFirst()->count())->toBe(2)
        ->and($first->relationsAsSecond()->count())->toBe(0)
        ->and($first->relatedProtocols()->pluck('id')->all())
        ->toBe([$second->id, $third->id])
        ->and($second->relatedProtocols()->pluck('id')->all())
        ->toBe([$first->id])
        ->and($third->relatedProtocols()->pluck('id')->all())
        ->toBe([$first->id])
        ->and($firstSecond->otherProtocol($first)->is($second))->toBeTrue()
        ->and($firstSecond->otherProtocol($second)->is($first))->toBeTrue();
});

test('relation scopes locate either side of the canonical pair', function () {
    $user = User::factory()->create();
    $first = createRelationFoundationProtocol($user, 141);
    $second = createRelationFoundationProtocol($user, 142);
    $unrelated = createRelationFoundationProtocol($user, 143);

    $relation = ProtocolRelation::connect($first, $second, $user);

    expect(ProtocolRelation::containing($first)->sole()->is($relation))->toBeTrue()
        ->and(ProtocolRelation::containing($second->id)->sole()->is($relation))->toBeTrue()
        ->and(ProtocolRelation::containing($unrelated)->doesntExist())->toBeTrue();
});

test('soft deletion preserves the relation but hides the trashed endpoint', function () {
    $user = User::factory()->create();
    $first = createRelationFoundationProtocol($user, 151);
    $second = createRelationFoundationProtocol($user, 152);
    $relation = ProtocolRelation::connect($first, $second, $user);

    $second->delete();

    expect(ProtocolRelation::find($relation->id))->not->toBeNull()
        ->and($first->relatedProtocols())->toHaveCount(0)
        ->and($relation->fresh()->secondProtocol->is($second))->toBeTrue();

    $second->restore();

    expect($first->relatedProtocols()->sole()->is($second))->toBeTrue();
});

test('permanent protocol deletion removes only its relation rows', function () {
    $user = User::factory()->create();
    $first = createRelationFoundationProtocol($user, 161);
    $second = createRelationFoundationProtocol($user, 162);
    ProtocolRelation::connect($first, $second, $user);

    $second->forceDelete();

    expect(ProtocolRelation::count())->toBe(0)
        ->and(Protocol::find($first->id))->not->toBeNull();
});

test('deleting the creator preserves the relation and clears its provenance', function () {
    $creator = User::factory()->create();
    $protocolOwner = User::factory()->create();
    $first = createRelationFoundationProtocol($protocolOwner, 171);
    $second = createRelationFoundationProtocol($protocolOwner, 172);
    $relation = ProtocolRelation::connect($first, $second, $creator);

    $creator->delete();

    expect($relation->fresh())->not->toBeNull()
        ->and($relation->fresh()->created_by)->toBeNull()
        ->and($relation->fresh()->creator)->toBeNull();
});
