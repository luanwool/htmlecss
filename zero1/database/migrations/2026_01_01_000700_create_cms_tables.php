<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique(); // hero, services, plans, team, gallery, testimonials, faq, location, cta
            $table->string('eyebrow')->nullable();
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->json('content')->nullable(); // campos específicos (labels de CTA, etc.)
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // termos-de-assinatura, privacidade, politicas
            $table->string('title');
            $table->longText('body')->nullable(); // Markdown, renderizado sem HTML bruto
            $table->string('meta_description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->text('content');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('source', 30)->nullable(); // google, presencial, instagram
            $table->date('reviewed_on')->nullable();
            // Depoimentos só podem ser publicados com autorização registrada.
            $table->timestamp('authorization_confirmed_at')->nullable();
            $table->string('authorization_note')->nullable();
            $table->foreignId('authorization_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique(); // ISO 1 = segunda ... 7 = domingo
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('site_settings');
    }
};
