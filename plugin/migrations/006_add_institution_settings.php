<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * Adds the institution settings of the section "Stud.App": support address,
 * logo, accent colour, links, mensa source and the switches for messages and
 * places.
 */
return new class extends Migration
{
    public const FIELDS = [
        'APP_CONNECT_FEATURE_MESSAGES' => [
            'type' => 'boolean',
            'value' => 1,
            'description' => 'Zeigt in der App die Nachrichten.',
        ],
        'APP_CONNECT_FEATURE_EVENT_PLACES' => [
            'type' => 'boolean',
            'value' => 1,
            'description' => 'Zeigt in der App bei Terminen den gebuchten Raum mit Karte und Raumfinder (Raumverwaltung von Stud.IP).',
        ],
        'APP_CONNECT_SUPPORT_EMAIL' => [
            'type' => 'string',
            'value' => '',
            'description' => 'E-Mail-Adresse des Supports der Hochschule; die App bietet sie für Hilfe und zum Melden von Inhalten an. Leer: kein Support-Kontakt der Hochschule in der App.',
        ],
        'APP_CONNECT_LOGO_URL' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Adresse des Hochschul-Logos für die App, beginnend mit https://.',
        ],
        'APP_CONNECT_ACCENT_COLOR' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Akzentfarbe der Hochschule als #RRGGBB. Die App leitet daraus ein kontrastsicheres Farbschema ab. Leer: Standardschema.',
        ],
        'APP_CONNECT_LINK_HOMEPAGE' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Link zur Website der Hochschule für die App, beginnend mit https://.',
        ],
        'APP_CONNECT_LINK_CAMPUS_MANAGEMENT' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Link zum Campus-Management-System der Hochschule, beginnend mit https://.',
        ],
        'APP_CONNECT_LINK_LIBRARY' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Link zur Bibliothek der Hochschule, beginnend mit https://.',
        ],
        'APP_CONNECT_LINK_IT_HELP' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Link zur IT-Hilfe der Hochschule, beginnend mit https://.',
        ],
        'APP_CONNECT_MENSA_OPENMENSA_IDS' => [
            'type' => 'string',
            'value' => '',
            'description' => 'OpenMensa-IDs der Mensen, durch Komma getrennt; die erste ist die voreingestellte. Hat Vorrang vor APP_CONNECT_MENSA_URL.',
        ],
        'APP_CONNECT_MENSA_URL' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Link zum Web-Speiseplan, wenn die Mensen nicht bei OpenMensa stehen, beginnend mit https://.',
        ],
    ];

    public function description()
    {
        return 'Adds the app-connect institution settings (support, branding, links, mensa, feature switches).';
    }

    protected function up()
    {
        foreach (self::FIELDS as $field => $definition) {
            if (!Config::get()->getMetadata($field)) {
                Config::get()->create($field, $definition + ['range' => 'global', 'section' => 'Stud.App']);
            }
        }
    }

    protected function down()
    {
        foreach (array_keys(self::FIELDS) as $field) {
            Config::get()->delete($field);
        }
    }
};
