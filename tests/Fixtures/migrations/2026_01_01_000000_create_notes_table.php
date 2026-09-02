<?php

declare(strict_types=1);

use Ract\Database\Migrations\Migration;
use Ract\Database\Schema\Blueprint;
use Ract\Database\Schema\SchemaBuilder;

return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('notes', static function (Blueprint $table): void {
            $table->id();
            $table->string('body');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('notes');
    }
};
