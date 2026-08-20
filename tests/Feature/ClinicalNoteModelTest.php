<?php

namespace Tests\Feature;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalNoteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_links_to_encounter_and_author(): void
    {
        $encounter = ClinicalEncounter::factory()->create();
        $author = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);

        $note = ClinicalNote::create([
            'encounter_id' => $encounter->id,
            'author_id' => $author->id,
            'chief_complaint' => 'Headache',
            'history_of_present_illness' => 'Two-day history.',
            'examination' => 'No acute findings.',
            'assessment' => 'Stable.',
            'diagnosis' => 'Tension headache.',
            'treatment_plan' => 'Hydration and review.',
        ]);

        $this->assertTrue($note->encounter->is($encounter));
        $this->assertTrue($note->author->is($author));
        $this->assertFalse($note->isFinalized());
    }

    public function test_note_can_be_finalized(): void
    {
        $note = ClinicalNote::factory()->create();

        $note->forceFill(['finalized_at' => now()])->save();

        $this->assertTrue($note->fresh()->isFinalized());
    }

    public function test_encounter_exposes_notes_and_vitals(): void
    {
        $encounter = ClinicalEncounter::factory()->create();
        ClinicalNote::factory()->create(['encounter_id' => $encounter->id]);

        $this->assertCount(1, $encounter->fresh()->notes);
        $this->assertIsIterable($encounter->vitals);
    }
}
