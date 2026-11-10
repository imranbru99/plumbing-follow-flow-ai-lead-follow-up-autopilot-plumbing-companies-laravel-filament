<?php

namespace App\Services;

use App\Models\Lead;

/**
 * Local equivalent of imrandevbd/laravel-ai-hub — draft/classify/extract for plumbing lead follow-ups.
 */
class AiHubService
{
    public function draftFollowUp(Lead $lead, string $channel = 'email'): array
    {
        $interest = $lead->service_interest ?: 'plumbing service';
        $name = $lead->name;

        $subject = match ($channel) {
            'sms' => null,
            default => "Quick follow-up on your {$interest} request — PlumbingFollowFlow",
        };

        $body = match ($channel) {
            'sms' => "Hi {$name}, this is your plumbing company following up on your {$interest} inquiry. Reply YES to book an estimate, or STOP to opt out.",
            default => "Hi {$name},\n\nThanks for reaching out about {$interest}. Our licensed plumbers can help with leak repairs, water heaters, drain cleaning, sewer lines, and emergency service.\n\nReply to this email or call us to schedule a convenient estimate window. If you prefer not to hear from us, reply STOP and we will remove you immediately.\n\n— Your PlumbingFollowFlow team",
        };

        $classification = $this->classifyLead($lead);

        return [
            'subject' => $subject,
            'body' => $body,
            'classification' => $classification['label'],
            'confidence' => $classification['confidence'],
        ];
    }

    public function classifyLead(Lead $lead): array
    {
        $text = strtolower(($lead->service_interest ?? '').' '.($lead->notes ?? '').' '.($lead->source ?? ''));

        if (str_contains($text, 'emergency') || str_contains($text, 'flood') || str_contains($text, 'burst') || str_contains($text, 'leak')) {
            return ['label' => 'urgent_qualified', 'confidence' => 96.5];
        }

        if (str_contains($text, 'water heater') || str_contains($text, 'sewer') || str_contains($text, 'repipe')) {
            return ['label' => 'high_value', 'confidence' => 91.0];
        }

        if (str_contains($text, 'quote') || str_contains($text, 'estimate')) {
            return ['label' => 'estimate_ready', 'confidence' => 88.0];
        }

        return ['label' => 'nurture', 'confidence' => 74.5];
    }

    public function classifyReply(string $reply): array
    {
        $text = strtolower($reply);

        if (preg_match('/\b(yes|interested|book|schedule|tomorrow|available)\b/', $text)) {
            return ['label' => 'qualified_hot', 'confidence' => 94.0, 'escalate' => true];
        }

        if (preg_match('/\b(stop|unsubscribe|remove|no)\b/', $text)) {
            return ['label' => 'opt_out', 'confidence' => 99.0, 'escalate' => false];
        }

        return ['label' => 'neutral', 'confidence' => 70.0, 'escalate' => false];
    }

    public function extractServiceSignals(string $text): array
    {
        $signals = [];
        foreach (['water heater', 'drain cleaning', 'sewer line', 'leak repair', 'repipe', 'slab leak', 'sump pump', 'fixture install'] as $term) {
            if (stripos($text, $term) !== false) {
                $signals[] = $term;
            }
        }

        return $signals;
    }
}
