<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Schema completo do Lectta. Cada tabela só é criada se ainda não existir, então
| rodar `php artisan migrate` num banco já populado não mexe em nada.
|
| Sem updated_at em lugar nenhum: os models usam $timestamps = false e o
| created_at vem do DEFAULT do banco.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 150);
                $table->string('email', 255)->unique('uniq_users_email');
                $table->string('password_hash', 255);
                $table->dateTime('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index('idx_subjects_user_id');
                $table->string('name', 100);
                $table->dateTime('created_at')->useCurrent();

                $table->unique(['user_id', 'name'], 'uniq_subject_user_name');
                $table->foreign('user_id', 'fk_subjects_user')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('topics')) {
            Schema::create('topics', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index('idx_topics_user_id');
                $table->unsignedInteger('subject_id')->index('idx_topics_subject_id');
                $table->string('name', 150);
                $table->dateTime('created_at')->useCurrent();

                $table->unique(['user_id', 'subject_id', 'name'], 'uniq_topic_user_subject_name');
                $table->foreign('user_id', 'fk_topics_user')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('subject_id', 'fk_topics_subject')->references('id')->on('subjects')->cascadeOnDelete();
            });
        }

        // Aulas. `subject`/`topic` guardam o nome por extenso (o que aparece na
        // tela); `subject_id`/`topic_id` ligam ao cadastro de matérias.
        if (! Schema::hasTable('classes')) {
            Schema::create('classes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index('idx_classes_user_id');
                $table->string('title', 255);
                $table->string('subject', 100)->default('Geral');
                $table->unsignedInteger('subject_id')->nullable()->index('idx_classes_subject_id');
                $table->unsignedInteger('topic_id')->nullable()->index('idx_classes_topic_id');
                $table->string('topic', 150)->nullable();
                $table->enum('status', ['queued_youtube', 'downloading', 'pending', 'processing', 'done', 'error'])
                    ->default('pending')->index('idx_classes_status');
                $table->string('audio_filename', 255)->nullable();
                $table->enum('source_type', ['upload', 'youtube', 'text'])->default('upload');
                $table->string('youtube_url', 500)->nullable();
                $table->longText('transcript')->nullable();
                $table->longText('summary_markdown')->nullable();
                $table->text('error_message')->nullable();
                // Progresso do pipeline incremental (JSON) -- ver LessonPipeline.
                $table->longText('pipeline_state')->nullable();
                $table->dateTime('pipeline_updated_at')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('processed_at')->nullable();

                $table->foreign('user_id', 'fk_classes_user')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('flashcards')) {
            Schema::create('flashcards', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('class_id')->index('idx_flashcards_class_id');
                $table->unsignedInteger('user_id')->index('idx_flashcards_user_id');
                $table->text('question');
                $table->text('answer');
                // Estado do SM-2.
                $table->float('ease_factor')->default(2.5);
                $table->integer('interval_days')->default(0);
                $table->integer('repetitions')->default(0);
                $table->date('due_date')->default(DB::raw('(CURRENT_DATE)'))->index('idx_flashcards_due');
                $table->dateTime('last_reviewed_at')->nullable();

                $table->foreign('class_id', 'fk_flashcards_class')->references('id')->on('classes')->cascadeOnDelete();
                $table->foreign('user_id', 'fk_flashcards_user')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Uma linha por avaliação -- base do streak e do heatmap.
        if (! Schema::hasTable('review_log')) {
            Schema::create('review_log', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('flashcard_id')->index('idx_review_log_flashcard_id');
                $table->tinyInteger('quality');
                $table->dateTime('reviewed_at')->useCurrent();

                $table->index(['user_id', 'reviewed_at'], 'idx_review_log_user_reviewed');
                $table->foreign('user_id', 'fk_review_log_user')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('flashcard_id', 'fk_review_log_flashcard')->references('id')->on('flashcards')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_log');
        Schema::dropIfExists('flashcards');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('users');
    }
};
