<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'audio_recording admite audios subidos desde la app: origen, hash de contenido e identificadores de Telegram opcionales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE audio_recording ADD source VARCHAR(16) DEFAULT 'telegram' NOT NULL");
        $this->addSql('ALTER TABLE audio_recording ADD content_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE audio_recording ALTER telegram_message_id DROP NOT NULL');
        $this->addSql('ALTER TABLE audio_recording ALTER telegram_file_unique_id DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EF0226531CDA8F7D ON audio_recording (content_hash)');
    }

    public function down(Schema $schema): void
    {
        // Solo es reversible si no hay audios subidos desde la app (sin identificadores de Telegram).
        $this->addSql('DROP INDEX UNIQ_EF0226531CDA8F7D');
        $this->addSql('ALTER TABLE audio_recording ALTER telegram_file_unique_id SET NOT NULL');
        $this->addSql('ALTER TABLE audio_recording ALTER telegram_message_id SET NOT NULL');
        $this->addSql('ALTER TABLE audio_recording DROP content_hash');
        $this->addSql('ALTER TABLE audio_recording DROP source');
    }
}
