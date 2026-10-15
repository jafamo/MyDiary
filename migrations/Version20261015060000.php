<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'audio_recording.device_name: dispositivo del token que subió el audio desde la app';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audio_recording ADD device_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audio_recording DROP device_name');
    }
}
