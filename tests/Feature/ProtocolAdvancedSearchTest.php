<?php

use App\Enums\ProtocolAssignmentPurpose;
use App\Enums\UserRole;
use App\Models\ArchiveFolder;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\ProtocolAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function createAdvancedSearchProtocol(User $creator, array $overrides = []): Protocol
{
    static $number = 1000;

    $number++;

    return Protocol::create(array_merge([
        'protocol_number' => $number,
        'protocol_year' => 2026,
        'protocol_date' => '2026-08-20',
        'direction' => 'incoming',
        'subject' => "Δοκιμαστικό πρωτόκολλο {$number}",
        'sender' => 'Δήμος Αθηναίων',
        'recipient' => null,
        'notes' => null,
        'archive_folder_id' => null,
        'created_by' => $creator->id,
    ], $overrides));
}

test('guest cannot use advanced protocol search', function () {
    $this->get(route('protocols.search'))
        ->assertRedirect(route('login'));
});

test('every authenticated role can open advanced search', function (
    UserRole $role
) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('protocols.search'))
        ->assertOk()
        ->assertViewIs('protocols.search')
        ->assertSee(__('search.title'))
        ->assertSee(route('protocols.search'), false)
        ->assertSee('name="field_3"', false);
})->with([
    'administrator' => UserRole::Administrator,
    'assigner' => UserRole::Assigner,
    'protocol officer' => UserRole::ProtocolOfficer,
    'viewer' => UserRole::Viewer,
]);

test('an empty search does not expose the entire protocol register', function () {
    $user = User::factory()->create(['role' => UserRole::Viewer]);
    $protocol = createAdvancedSearchProtocol($user);

    $this->actingAs($user)
        ->get(route('protocols.search'))
        ->assertOk()
        ->assertViewHas('hasCriteria', false)
        ->assertViewHas('protocols', null)
        ->assertSee(__('search.results.initial'))
        ->assertDontSee($protocol->subject);
});

test('unused criterion rows ignore empty checkboxes without validation errors', function () {
    $user = User::factory()->create(['role' => UserRole::Viewer]);
    $protocol = createAdvancedSearchProtocol($user);

    $this->actingAs($user)
        ->get(route('protocols.search', [
            'empty_1' => 1,
            'empty_2' => 1,
            'empty_3' => 1,
        ]))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertViewHas('hasCriteria', false)
        ->assertViewHas('protocols', null)
        ->assertSee(__('search.results.initial'))
        ->assertDontSee($protocol->subject);
});

test('criterion controls prevent an ambiguous empty selection in the browser', function () {
    $user = User::factory()->create(['role' => UserRole::Viewer]);

    $this->actingAs($user)
        ->get(route('protocols.search'))
        ->assertOk()
        ->assertSee(__('search.criteria.empty'))
        ->assertSee('data-search-field="1"', false)
        ->assertSee('data-search-term="1"', false)
        ->assertSee('data-search-empty="1"', false)
        ->assertSee('emptyInput.disabled = ! hasField;', false)
        ->assertSee("termInput.value = '';", false)
        ->assertSee('termInput.disabled = true;', false);
});

test('exact number and year redirect directly to an active protocol', function () {
    $user = User::factory()->create(['role' => UserRole::Viewer]);
    $protocol = createAdvancedSearchProtocol($user, [
        'protocol_number' => 47,
        'protocol_year' => 2025,
    ]);

    $this->actingAs($user)
        ->get(route('protocols.search', [
            'exact_number' => 47,
            'exact_year' => 2025,
        ]))
        ->assertRedirect(route('protocols.show', $protocol));

    $protocol->delete();

    $this->actingAs($user)
        ->get(route('protocols.search', [
            'exact_number' => 47,
            'exact_year' => 2025,
        ]))
        ->assertOk()
        ->assertSee(__('search.exact.not_found'));
});

test('number year date and direction filters are combined', function () {
    $user = User::factory()->create(['role' => UserRole::Assigner]);

    $matching = createAdvancedSearchProtocol($user, [
        'protocol_number' => 25,
        'protocol_year' => 2026,
        'protocol_date' => '2026-04-10',
        'direction' => 'outgoing',
        'subject' => 'Μοναδικό αποτέλεσμα εύρους',
    ]);
    createAdvancedSearchProtocol($user, [
        'protocol_number' => 26,
        'protocol_year' => 2026,
        'protocol_date' => '2026-04-10',
        'direction' => 'incoming',
        'subject' => 'Λάθος κατεύθυνση',
    ]);
    createAdvancedSearchProtocol($user, [
        'protocol_number' => 27,
        'protocol_year' => 2025,
        'protocol_date' => '2026-04-10',
        'direction' => 'outgoing',
        'subject' => 'Λάθος έτος',
    ]);

    $this->actingAs($user)
        ->get(route('protocols.search', [
            'number_from' => 20,
            'number_to' => 30,
            'protocol_year' => 2026,
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'direction' => 'outgoing',
        ]))
        ->assertOk()
        ->assertSee($matching->subject)
        ->assertDontSee('Λάθος κατεύθυνση')
        ->assertDontSee('Λάθος έτος');
});

test('three arbitrary field criteria are combined with logical and', function () {
    $user = User::factory()->create(['role' => UserRole::ProtocolOfficer]);

    $matching = createAdvancedSearchProtocol($user, [
        'subject' => 'Πρόσκληση σχολικής επιτροπής',
        'sender' => 'Δήμος Πατρέων',
        'notes' => 'Επείγον έγγραφο',
    ]);
    createAdvancedSearchProtocol($user, [
        'subject' => 'Πρόσκληση σχολικής επιτροπής',
        'sender' => 'Δήμος Πατρέων',
        'notes' => 'Χωρίς προτεραιότητα',
    ]);

    $this->actingAs($user)
        ->get(route('protocols.search', [
            'field_1' => 'subject',
            'term_1' => 'σχολικής',
            'field_2' => 'sender',
            'term_2' => 'Πατρέων',
            'field_3' => 'notes',
            'term_3' => 'Επείγον',
        ]))
        ->assertOk()
        ->assertSee($matching->subject)
        ->assertDontSee('Χωρίς προτεραιότητα');
});

test('relationship criteria search folders attachments and current officers', function () {
    $administrator = User::factory()->create([
        'role' => UserRole::Administrator,
    ]);
    $officer = User::factory()->create([
        'name' => 'Μαρία Διεκπεραίωσης',
        'role' => UserRole::ProtocolOfficer,
    ]);
    $folder = ArchiveFolder::factory()->create([
        'code' => 'Φ.12.3',
        'description' => 'Σχολικές εκδρομές',
    ]);
    $protocol = createAdvancedSearchProtocol($administrator, [
        'subject' => 'Πρωτόκολλο με σχέσεις',
        'archive_folder_id' => $folder->id,
    ]);

    ProtocolAttachment::create([
        'protocol_id' => $protocol->id,
        'original_name' => 'egkrisi-ekdromis.pdf',
        'file_path' => "protocols/{$protocol->id}/egkrisi-ekdromis.pdf",
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'uploaded_by' => $administrator->id,
    ]);
    ProtocolAssignment::create([
        'protocol_id' => $protocol->id,
        'purpose' => ProtocolAssignmentPurpose::Processing,
        'assigned_by' => $administrator->id,
        'assigned_to' => $officer->id,
        'due_at' => null,
        'completed_at' => null,
        'superseded_at' => null,
    ]);

    $this->actingAs($administrator)
        ->get(route('protocols.search', [
            'field_1' => 'archive_folder',
            'term_1' => 'εκδρομές',
            'field_2' => 'attachment_name',
            'term_2' => 'egkrisi',
            'field_3' => 'processing_officer',
            'term_3' => 'Μαρία',
        ]))
        ->assertOk()
        ->assertSee($protocol->subject);
});

test('empty criteria and invalid query input are handled safely', function () {
    $user = User::factory()->create(['role' => UserRole::Viewer]);
    $emptyNotes = createAdvancedSearchProtocol($user, [
        'subject' => 'Χωρίς σημειώσεις',
        'notes' => null,
    ]);
    createAdvancedSearchProtocol($user, [
        'subject' => 'Με σημειώσεις',
        'notes' => 'Υπάρχουν σημειώσεις',
    ]);

    $this->actingAs($user)
        ->get(route('protocols.search', [
            'field_1' => 'notes',
            'empty_1' => 1,
        ]))
        ->assertOk()
        ->assertSee($emptyNotes->subject)
        ->assertDontSee('Με σημειώσεις');

    $this->actingAs($user)
        ->from(route('protocols.search'))
        ->get(route('protocols.search', [
            'field_1' => 'database_column',
            'term_1' => 'unsafe',
            'date_from' => '2026-08-20',
            'date_to' => '2026-08-19',
        ]))
        ->assertRedirect(route('protocols.search'))
        ->assertSessionHasErrors(['field_1', 'date_to']);
});

test('advanced search paginates results and preserves its query string', function () {
    $user = User::factory()->create(['role' => UserRole::Viewer]);

    foreach (range(1, 16) as $position) {
        createAdvancedSearchProtocol($user, [
            'subject' => "Αποτέλεσμα σελιδοποίησης {$position}",
        ]);
    }

    $this->actingAs($user)
        ->get(route('protocols.search', [
            'field_1' => 'subject',
            'term_1' => 'Αποτέλεσμα σελιδοποίησης',
        ]))
        ->assertOk()
        ->assertViewHas('protocols', function ($protocols): bool {
            return $protocols->count() === 15
                && $protocols->total() === 16;
        })
        ->assertSee('field_1=subject', false)
        ->assertSee('page=2', false);
});
