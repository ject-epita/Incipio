<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210905154128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE Cca (id INT AUTO_INCREMENT NOT NULL, signataire1_id INT DEFAULT NULL, signataire2_id INT DEFAULT NULL, prospect_id INT NOT NULL, version INT DEFAULT NULL, redige TINYINT(1) DEFAULT NULL, relu TINYINT(1) DEFAULT NULL, spt1 TINYINT(1) DEFAULT NULL, spt2 TINYINT(1) DEFAULT NULL, dateSignature DATETIME DEFAULT NULL, envoye TINYINT(1) DEFAULT NULL, receptionne TINYINT(1) DEFAULT NULL, generer INT DEFAULT NULL, dateFin DATE NOT NULL, nom VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_F9F883216C6E55B5 (nom), INDEX IDX_F9F88321C71184C3 (signataire1_id), INDEX IDX_F9F88321D5A42B2D (signataire2_id), INDEX IDX_F9F88321D182060A (prospect_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE Cca ADD CONSTRAINT FK_F9F88321C71184C3 FOREIGN KEY (signataire1_id) REFERENCES Personne (id)');
        $this->addSql('ALTER TABLE Cca ADD CONSTRAINT FK_F9F88321D5A42B2D FOREIGN KEY (signataire2_id) REFERENCES Personne (id)');
        $this->addSql('ALTER TABLE Cca ADD CONSTRAINT FK_F9F88321D182060A FOREIGN KEY (prospect_id) REFERENCES Prospect (id)');

        // Update all previous CE with type=TYPE_CE(=0) because none are BDC yet
        $this->addSql('ALTER TABLE Ce ADD type SMALLINT NOT NULL');
        $this->addSql('UPDATE Ce SET type=0 WHERE type=NULL');

        // Add Cca id in Ce/Bdc
        $this->addSql('ALTER TABLE Ce ADD cca_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Ce ADD CONSTRAINT FK_A7559BEEFBAA5D8E FOREIGN KEY (cca_id) REFERENCES Cca (id)');
        $this->addSql('CREATE INDEX IDX_A7559BEEFBAA5D8E ON Ce (cca_id)');

        // Drop thread in DocType
        $this->addSql('ALTER TABLE Ap DROP FOREIGN KEY FK_F8BE1D87E2904019');
        $this->addSql('DROP INDEX UNIQ_F8BE1D87E2904019 ON Ap');
        $this->addSql('ALTER TABLE Ap DROP thread_id');
        $this->addSql('ALTER TABLE Av DROP FOREIGN KEY FK_11DDB8B2E2904019');
        $this->addSql('DROP INDEX UNIQ_11DDB8B2E2904019 ON Av');
        $this->addSql('ALTER TABLE Av DROP thread_id');
        $this->addSql('ALTER TABLE AvMission DROP FOREIGN KEY FK_87698F23E2904019');
        $this->addSql('DROP INDEX UNIQ_87698F23E2904019 ON AvMission');
        $this->addSql('ALTER TABLE AvMission DROP thread_id');
        $this->addSql('ALTER TABLE Cc DROP FOREIGN KEY FK_4E363EDBE2904019');
        $this->addSql('DROP INDEX UNIQ_4E363EDBE2904019 ON Cc');
        $this->addSql('ALTER TABLE Cc DROP thread_id');
        $this->addSql('ALTER TABLE Ce DROP FOREIGN KEY FK_A7559BEEE2904019');
        $this->addSql('DROP INDEX UNIQ_A7559BEEE2904019 ON Ce');
        $this->addSql('ALTER TABLE Ce DROP thread_id');
        $this->addSql('ALTER TABLE Mission DROP FOREIGN KEY FK_5FDACBA0E2904019');
        $this->addSql('DROP INDEX UNIQ_5FDACBA0E2904019 ON Mission');
        $this->addSql('ALTER TABLE Mission DROP thread_id');
        $this->addSql('ALTER TABLE ProcesVerbal DROP FOREIGN KEY FK_D8EBE2BFE2904019');
        $this->addSql('DROP INDEX UNIQ_D8EBE2BFE2904019 ON ProcesVerbal');
        $this->addSql('ALTER TABLE ProcesVerbal DROP thread_id');

        // Phase in repartition JEH
        $this->addSql('ALTER TABLE RepartitionJEH ADD phase_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE RepartitionJEH ADD CONSTRAINT FK_5E061BA899091188 FOREIGN KEY (phase_id) REFERENCES Phase (id)');
        $this->addSql('CREATE INDEX IDX_5E061BA899091188 ON RepartitionJEH (phase_id)');

        $this->addSql('ALTER TABLE Etude ADD cca_id INT DEFAULT NULL, ADD auditCommentaire LONGTEXT DEFAULT NULL, ADD ccaActive TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE Etude ADD CONSTRAINT FK_DC1F8620FBAA5D8E FOREIGN KEY (cca_id) REFERENCES Cca (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_DC1F8620FBAA5D8E ON Etude (cca_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Etude DROP FOREIGN KEY FK_DC1F8620FBAA5D8E');
        $this->addSql('DROP TABLE Cca');
        $this->addSql('ALTER TABLE Ap ADD thread_id VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE Ap ADD CONSTRAINT FK_F8BE1D87E2904019 FOREIGN KEY (thread_id) REFERENCES Thread (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F8BE1D87E2904019 ON Ap (thread_id)');
        $this->addSql('ALTER TABLE Av ADD thread_id VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE Av ADD CONSTRAINT FK_11DDB8B2E2904019 FOREIGN KEY (thread_id) REFERENCES Thread (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_11DDB8B2E2904019 ON Av (thread_id)');
        $this->addSql('ALTER TABLE AvMission ADD thread_id VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE AvMission ADD CONSTRAINT FK_87698F23E2904019 FOREIGN KEY (thread_id) REFERENCES Thread (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87698F23E2904019 ON AvMission (thread_id)');
        $this->addSql('ALTER TABLE Cc ADD thread_id VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE Cc ADD CONSTRAINT FK_4E363EDBE2904019 FOREIGN KEY (thread_id) REFERENCES Thread (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4E363EDBE2904019 ON Cc (thread_id)');
        $this->addSql('ALTER TABLE Ce ADD thread_id VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, DROP type');
        $this->addSql('ALTER TABLE Ce ADD CONSTRAINT FK_A7559BEEE2904019 FOREIGN KEY (thread_id) REFERENCES Thread (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A7559BEEE2904019 ON Ce (thread_id)');
        $this->addSql('DROP INDEX IDX_DC1F8620FBAA5D8E ON Etude');
        $this->addSql('ALTER TABLE Etude DROP cca_id, DROP auditCommentaire, DROP ccaActive');
        $this->addSql('ALTER TABLE Mission ADD thread_id VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE Mission ADD CONSTRAINT FK_5FDACBA0E2904019 FOREIGN KEY (thread_id) REFERENCES Thread (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5FDACBA0E2904019 ON Mission (thread_id)');
        $this->addSql('ALTER TABLE ProcesVerbal ADD thread_id VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE ProcesVerbal ADD CONSTRAINT FK_D8EBE2BFE2904019 FOREIGN KEY (thread_id) REFERENCES Thread (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D8EBE2BFE2904019 ON ProcesVerbal (thread_id)');
        $this->addSql('ALTER TABLE RepartitionJEH DROP FOREIGN KEY FK_5E061BA899091188');
        $this->addSql('DROP INDEX IDX_5E061BA899091188 ON RepartitionJEH');
        $this->addSql('ALTER TABLE RepartitionJEH DROP phase_id');
        $this->addSql('ALTER TABLE Ce DROP FOREIGN KEY FK_A7559BEEFBAA5D8E');
        $this->addSql('DROP INDEX IDX_A7559BEEFBAA5D8E ON Ce');
        $this->addSql('ALTER TABLE Ce DROP cca_id');
    }
}
