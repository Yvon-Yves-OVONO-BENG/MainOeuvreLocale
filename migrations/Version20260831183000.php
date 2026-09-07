<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les tickets de 3 contacts par profession, fixe Pro à 200 FCFA et Premium à 2 000 FCFA.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE contact_ticket (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, profession_id INT NOT NULL, quantity INT NOT NULL, remaining_contacts INT NOT NULL, price INT NOT NULL, payment_id VARCHAR(120) NOT NULL, payment_method VARCHAR(30) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CONTACT_TICKET_PAYMENT (payment_id), INDEX IDX_CONTACT_TICKET_USER (user_id), INDEX IDX_CONTACT_TICKET_PROFESSION (profession_id), INDEX idx_contact_ticket_available (user_id, profession_id, is_active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE contact_ticket ADD CONSTRAINT FK_CONTACT_TICKET_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_ticket ADD CONSTRAINT FK_CONTACT_TICKET_PROFESSION FOREIGN KEY (profession_id) REFERENCES profession (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE contact_log ADD ticket_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_log ADD CONSTRAINT FK_CONTACT_LOG_TICKET FOREIGN KEY (ticket_id) REFERENCES contact_ticket (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CONTACT_LOG_TICKET ON contact_log (ticket_id)');

        $this->addSql("UPDATE plan SET price = 200, max_contacts = 0, can_chat = 0, description = 'Achetez un ticket de 3 contacts dans une même profession, sans abonnement mensuel.', features = '[\"3 contacts par ticket\", \"Une profession au choix\", \"Sans abonnement mensuel\"]' WHERE slug = 'pro'");
        $this->addSql("UPDATE plan_duration pd INNER JOIN plan p ON p.id = pd.plan_id SET pd.price = 200, pd.months = 0, pd.label = '1 ticket - 3 contacts', pd.discount = 0 WHERE p.slug = 'pro'");

        $this->addSql("UPDATE plan SET price = 2000 WHERE slug = 'premium'");
        $this->addSql("UPDATE plan_duration pd INNER JOIN plan p ON p.id = pd.plan_id SET pd.price = ROUND(pd.price * 0.4) WHERE p.slug = 'premium'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_log DROP FOREIGN KEY FK_CONTACT_LOG_TICKET');
        $this->addSql('DROP INDEX IDX_CONTACT_LOG_TICKET ON contact_log');
        $this->addSql('ALTER TABLE contact_log DROP ticket_id');
        $this->addSql('DROP TABLE contact_ticket');

        $this->addSql("UPDATE plan SET price = 2000, max_contacts = 20, can_chat = 1 WHERE slug = 'pro'");
        $this->addSql("UPDATE plan_duration pd INNER JOIN plan p ON p.id = pd.plan_id SET pd.price = 2000, pd.months = 1, pd.label = '1 mois' WHERE p.slug = 'pro'");
        $this->addSql("UPDATE plan SET price = 5000 WHERE slug = 'premium'");
        $this->addSql("UPDATE plan_duration pd INNER JOIN plan p ON p.id = pd.plan_id SET pd.price = ROUND(pd.price / 0.4) WHERE p.slug = 'premium'");
    }
}
