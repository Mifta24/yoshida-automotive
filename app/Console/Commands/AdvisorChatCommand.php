<?php

namespace App\Console\Commands;

use App\Models\Workshop;
use App\Services\Advisor\AdvisorService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('advisor:chat {workshop? : Workshop slug} {--locale=id : id|en|ja}')]
#[Description('Talk to the AI Advisor for a workshop from the terminal, for testing the tool-calling loop before the web UI exists.')]
class AdvisorChatCommand extends Command
{
    public function handle(AdvisorService $service): int
    {
        $workshop = $this->argument('workshop')
            ? Workshop::where('slug', $this->argument('workshop'))->first()
            : Workshop::first();

        if (! $workshop) {
            $this->error('No workshop found. Seed one first: php artisan db:seed');

            return self::FAILURE;
        }

        if (blank(config('services.local_llm.base_url'))) {
            $this->error('LOCAL_LLM_BASE_URL is not set in .env — the advisor has no model endpoint to call.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');
        $conversation = $service->startConversation($workshop, $locale);

        $this->info("Chatting with the AI Advisor for {$workshop->name} ({$locale}). Type 'exit' to quit.");
        $this->newLine();

        while (true) {
            $customerMessage = $this->ask('You');

            if ($customerMessage === null || in_array(trim($customerMessage), ['exit', 'quit'], true)) {
                break;
            }

            $message = $service->reply($workshop, $conversation, $customerMessage);

            $this->newLine();
            $this->line('<fg=cyan>Advisor:</> '.($message->content ?? '(no text — see UI payload below)'));

            if ($message->ui_payload) {
                $this->line('<fg=gray>[ui_payload] '.json_encode($message->ui_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</>');
            }

            $conversation->refresh();
            if ($conversation->isHandedOver()) {
                $this->warn('Conversation handed over to human staff. Ending session.');
                break;
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
