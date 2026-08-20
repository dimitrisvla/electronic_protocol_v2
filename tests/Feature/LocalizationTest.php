<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Enums\UserRole;

class LocalizationTest extends TestCase
{
    public function test_application_uses_greek_as_its_default_locale(): void
    {
        $this->assertSame('el', config('app.locale'));
        $this->assertSame('el', app()->getLocale());
    }


    public function test_user_roles_have_greek_display_labels(): void
    {
        $this->assertSame(
            'Διαχειριστής',
            UserRole::Administrator->label()
        );

        $this->assertSame(
            'Υπεύθυνος Ανάθεσης',
            UserRole::Assigner->label()
        );

        $this->assertSame(
            'Συγγραφέας',
            UserRole::ProtocolOfficer->label()
        );

        $this->assertSame(
            'Αναγνώστης',
            UserRole::Viewer->label()
        );

        $this->assertSame('administrator', UserRole::Administrator->value);
        $this->assertSame('assigner', UserRole::Assigner->value);
        $this->assertSame('protocol_officer', UserRole::ProtocolOfficer->value);
        $this->assertSame('viewer', UserRole::Viewer->value);
    }


    public function test_validation_messages_and_attribute_names_are_greek(): void
    {
        $validator = Validator::make(
            [
                'direction' => 'invalid-direction',
            ],

            [
                'protocol_number' => ['required'],
                'direction' => ['in:incoming,outgoing'],
            ]
        );

        $this->assertSame(
            'Το πεδίο αριθμός πρωτοκόλλου είναι υποχρεωτικό.',
            $validator->errors()->first('protocol_number')
        );

        $this->assertSame(
            'Η επιλεγμένη τιμή για το πεδίο κατεύθυνση δεν είναι έγκυρη.',
            $validator->errors()->first('direction')
        );
    }




    public function test_greek_translation_files_are_loaded(): void
    {
        $this->assertSame(
            'Ηλεκτρονικό Πρωτόκολλο',
            __('common.app_name')
        );

        $this->assertSame(
            'Αποθήκευση',
            __('common.actions.save')
        );
    }



    public function test_framework_messages_are_available_in_greek(): void
    {
        $this->assertSame(
            'Τα στοιχεία σύνδεσης δεν αντιστοιχούν στα αρχεία μας.',
          __('auth.failed')
        );

        $this->assertSame(
            'Ο κωδικός πρόσβασης που δώσατε είναι λανθασμένος.',
            __('auth.password')
    )   ;

        $this->assertSame(
            'Έχουν πραγματοποιηθεί πάρα πολλές προσπάθειες σύνδεσης. Δοκιμάστε ξανά σε 30 δευτερόλεπτα.',
            __('auth.throttle', ['seconds' => 30])
        );

        $this->assertSame(
            'Ο κωδικός πρόσβασής σας επαναφέρθηκε.',
            __('passwords.reset')
         );  

         $this->assertSame(
            '&laquo; Προηγούμενη',
            __('pagination.previous')
         );

        $this->assertSame(
            'Επόμενη &raquo;',
            __('pagination.next')
        ); 

    }


    public function test_shared_navigation_has_greek_translations(): void
    {
        $this->assertSame(
            'Κύρια πλοήγηση',
            __('common.navigation.aria_label')
        );

        $this->assertSame(
            'Πρωτόκολλα',
            __('common.navigation.protocols')
        );

        $this->assertSame(
            'Εποπτεία Αναθέσεων',
            __('common.navigation.assignment_oversight')
        );

        $this->assertSame(
            'Προς Ενημέρωση',
            __('common.navigation.for_information')
        );

        $this->assertSame(
            'Οι Εργασίες Διεκπεραίωσής μου',
            __('common.navigation.my_processing_work')
        );

        $this->assertSame(
            'Νέο Πρωτόκολλο',
            __('common.navigation.create_protocol')
        );

        $this->assertSame(
            'Διαχείριση Χρηστών',
            __('common.navigation.user_management')
        );

        $this->assertSame(
            'Συνδεδεμένος χρήστης: Dimitris',
            __('common.navigation.logged_in_as', ['name' => 'Dimitris'])
        );

        $this->assertSame('Έξοδος', __('common.navigation.logout'));
        $this->assertSame('Είσοδος', __('common.navigation.login'));
        $this->assertSame('Εγγραφή', __('common.navigation.register'));
    }



    public function test_protocol_directions_have_greek_display_labels(): void
    {
        $this->assertSame(
            'Κατεύθυνση',
            __('protocols.directions.label')
        );

        $this->assertSame(
            'Επιλέξτε κατεύθυνση',
            __('protocols.directions.select')
        );

        $this->assertSame(
            'Όλες οι κατευθύνσεις',
            __('protocols.directions.all')
        );

        $this->assertSame(
            'Εισερχόμενο',
            __('protocols.directions.incoming')
        );

        $this->assertSame(
            'Εξερχόμενο',
            __('protocols.directions.outgoing')
        );
    }


}
