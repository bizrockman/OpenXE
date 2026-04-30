<?php

namespace Xentral\Modules\Api\Resource;

use Xentral\Components\Database\SqlQuery\SelectQuery;
use Xentral\Components\Database\SqlQuery\UpdateQuery;

class ArticleResource extends AbstractResource
{
    const TABLE_NAME = 'artikel';

    /**
     * System-managed columns the API must never touch on update/insert.
     * Reasons: primary key, audit / change-tracking, computed caches,
     * timestamps that the application sets itself, and obsolete shop
     * fields whose use is being phased out via the artikel_shop table.
     */
    private const SYSTEM_FIELDS = [
        'id',
        'checksum',
        'hinzugefuegt',
        'usereditid',
        'useredittimestamp',
        'intern_gesperrtuser',
        'inbearbeitunguser',
        'inbearbeitung',
        'logdatei',
        'laststorage_changed',
        'laststorage_sync',
        'cache_lagerplatzinhaltmenge',
        'shop',
        'shop2',
        'shop3',
    ];

    /**
     * Every flat scalar column on the `artikel` table that the API allows
     * a client to write through PUT. We deliberately list these explicitly
     * rather than discovering them via INFORMATION_SCHEMA, so adding a new
     * column to the database does not silently make it writable through
     * the public API.
     *
     * Notes on a few fields a caller is most likely to set:
     * - `typ`        : the article category (UI dropdown). Value format
     *                  is "<artikelkategorien.id>_kat", e.g. "2_kat".
     *                  Legacy fallback keys exist (e.g. "produkt",
     *                  "module") when artikelkategorien is empty.
     * - `nummer`     : article number, must be unique on the table.
     * - `geloescht`  : 0 / 1; soft-delete marker.
     * - `warengruppe`: free-text category label, NOT a foreign key.
     */
    private const WRITABLE_FIELDS = [
        // Identifying / classification
        'typ', 'nummer', 'projekt', 'inaktiv', 'ausverkauft', 'warengruppe',
        'klasse', 'adresse', 'firma', 'kostenstelle', 'abckategorie',

        // Names / descriptions (de + en)
        'name_de', 'name_en', 'kurztext_de', 'kurztext_en',
        'beschreibung_de', 'beschreibung_en', 'uebersicht_de', 'uebersicht_en',
        'links_de', 'links_en', 'startseite_de', 'startseite_en',
        'sonderaktion', 'sonderaktion_en',
        'anabregs_text', 'anabregs_text_en', 'hinweis_einfuegen',
        'metatitle_de', 'metatitle_en',
        'metadescription_de', 'metadescription_en',
        'metakeywords_de', 'metakeywords_en',
        'katalogtext_de', 'katalogtext_en',
        'katalogbezeichnung_de', 'katalogbezeichnung_en',

        // Visuals / media
        'standardbild', 'bildvorschau',

        // Manufacturer / barcode / external IDs
        'hersteller', 'herstellerlink', 'herstellernummer', 'barcode', 'ean',
        'webid', 'pcbdecal',

        // Inventory / logistics
        'lager_platz', 'lagerartikel', 'lieferzeit', 'lieferzeitmanuell',
        'lieferzeitmanuell_en', 'mindestlager', 'mindestbestellung',
        'pseudolager', 'lagerkorrekturwert', 'autolagerlampe',
        'mindesthaltbarkeitsdatum', 'inventursperre', 'inventurekaktiv',
        'inventurek', 'restmenge', 'nachbestellt', 'autobestellung',
        'autoabgleicherlaubt',

        // Physical / shipping
        'gewicht', 'nettogewicht', 'laenge', 'breite', 'hoehe',
        'porto', 'gebuehr', 'einheit', 'herkunftsland', 'ursprungsregion',
        'zolltarifnummer',

        // Production / parts
        'teilbar', 'nteile', 'seriennummern', 'letzteseriennummer',
        'stueckliste', 'juststueckliste', 'endmontage', 'funktionstest',
        'artikelcheckliste', 'produktion', 'produktioninfo',
        'externeproduktion', 'chargenverwaltung',
        'has_preproduced_partlist', 'preproduced_partlist',

        // Variants
        'variante', 'variante_von', 'variante_kopie', 'unikat',
        'unikatbeikopie', 'matrixprodukt', 'individualartikel',
        'generierenummerbeioption',

        // Sales / shop
        'shopartikel', 'unishopartikel', 'journalshopartikel', 'katalog',
        'startseite', 'topseller', 'neu', 'wichtig',
        'partnerprogramm_sperre', 'keineeinzelartikelanzeigen', 'allelieferanten', 'downloadartikel',

        // Pricing / VAT / accounts
        'umsatzsteuer', 'steuersatz', 'pseudopreis', 'rabatt', 'rabatt_prozent',
        'keinrabatterlaubt', 'keinskonto', 'tagespreise',
        'berechneterek', 'verwendeberechneterek', 'berechneterekwaehrung',
        'artikelautokalkulation', 'artikelabschliessenkalkulation',
        'artikelfifokalkulation', 'kontorahmen', 'steuergruppe', 'xvp',
        'ohnepreisimpdf', 'vkmeldungunterdruecken',

        // Tax routing — Sachkonten per scenario
        'steuer_erloese_inland_normal', 'steuer_aufwendung_inland_normal',
        'steuer_erloese_inland_ermaessigt', 'steuer_aufwendung_inland_ermaessigt',
        'steuer_erloese_inland_steuerfrei', 'steuer_aufwendung_inland_steuerfrei',
        'steuer_erloese_inland_innergemeinschaftlich',
        'steuer_aufwendung_inland_innergemeinschaftlich',
        'steuer_erloese_inland_eunormal', 'steuer_aufwendung_inland_eunormal',
        'steuer_erloese_inland_euermaessigt', 'steuer_aufwendung_inland_euermaessigt',
        'steuer_erloese_inland_nichtsteuerbar',
        'steuer_aufwendung_inland_nichtsteuerbar',
        'steuer_erloese_inland_export', 'steuer_aufwendung_inland_import',
        'steuer_art_produkt', 'steuer_art_produkt_download',
        'steuertext_innergemeinschaftlich', 'steuertext_export',

        // Status / lifecycle
        'gesperrt', 'sperrgrund', 'geloescht', 'gueltigbis',
        'intern_gesperrt', 'intern_gesperrtgrund',
        'internerkommentar', 'internkommentar',
        'freigabenotwendig', 'freigaberegel', 'altersfreigabe',
        'provisionssperre', 'provisionsartikel',
        'serviceartikel', 'dienstleistung', 'geraet',

        // MLM / commissions
        'mlmpunkte', 'mlmbonuspunkte', 'mlmdirektpraemie',
        'mlmkeinepunkteeigenkauf',

        // Sundry
        'sonstiges', 'leerfeld', 'rohstoffe',
        'etikettautodruck', 'autodrucketikett',
        'formelmenge', 'formelpreis',
        'bestandalternativartikel',

        // Free text fields 1..40
        'freifeld1', 'freifeld2', 'freifeld3', 'freifeld4', 'freifeld5',
        'freifeld6', 'freifeld7', 'freifeld8', 'freifeld9', 'freifeld10',
        'freifeld11', 'freifeld12', 'freifeld13', 'freifeld14', 'freifeld15',
        'freifeld16', 'freifeld17', 'freifeld18', 'freifeld19', 'freifeld20',
        'freifeld21', 'freifeld22', 'freifeld23', 'freifeld24', 'freifeld25',
        'freifeld26', 'freifeld27', 'freifeld28', 'freifeld29', 'freifeld30',
        'freifeld31', 'freifeld32', 'freifeld33', 'freifeld34', 'freifeld35',
        'freifeld36', 'freifeld37', 'freifeld38', 'freifeld39', 'freifeld40',
    ];

    /** @var \Api $legacyApi */
    private $legacyApi;

    /**
     * @param \Api $api
     *
     * @return void
     */
    public function setLegacyApi($api)
    {
        $this->legacyApi = $api;
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setTableName(self::TABLE_NAME);

        $this->registerFilterParams([
            'typ' => 'a.typ LIKE',
            'name_de' => 'a.name_de %LIKE%',
            'name_de_exakt' => 'a.name_de LIKE',
            'name_de_startswith' => 'a.name_de LIKE%',
            'name_de_endswith' => 'a.name_de %LIKE',
            'name_de_equals' => 'a.name_de LIKE',
            'name_en' => 'a.name_en %LIKE%',
            'name_en_exakt' => 'a.name_en LIKE',
            'name_en_startswith' => 'a.name_en LIKE%',
            'name_en_endswith' => 'a.name_en %LIKE',
            'name_en_equals' => 'a.name_en LIKE',
            'nummer' => 'a.nummer %LIKE%',
            'nummer_exakt' => 'a.nummer LIKE',
            'nummer_startswith' => 'a.nummer LIKE%',
            'nummer_endswith' => 'a.nummer %LIKE',
            'nummer_equals' => 'a.nummer LIKE',
            'projekt' => 'a.projekt =',
            'adresse' => 'a.adresse =',
            'katalog' => 'a.katalog =',
            'firma' => 'a.firma =',
            'ausverkauft' => 'a.ausverkauft =',
            'startseite' => 'a.startseite =',
            'topseller' => 'a.topseller =',
        ]);

        $this->registerSortingParams([
            'name_de' => 'a.name_de',
            'name_en' => 'a.name_en',
            'nummer' => 'a.nummer',
            'typ' => 'a.typ',
        ]);

        // Build the rules array programmatically from the constants above:
        // every system field is locked, every writable field is allowed
        // through with no further constraint (Rakit treats an empty rule
        // as "field is optional, accept any value"), and a small handful
        // of fields get specific constraints.
        $rules = [];
        foreach (self::SYSTEM_FIELDS as $field) {
            $rules[$field] = 'not_present';
        }
        foreach (self::WRITABLE_FIELDS as $field) {
            $rules[$field] = '';
        }

        // Field-specific overrides
        $rules['nummer']      = 'unique:artikel,nummer';   // dropped 'required' to allow partial PUT
        $rules['projekt']     = 'numeric';
        $rules['adresse']     = 'numeric';
        $rules['katalog']     = 'numeric';
        $rules['firma']       = 'numeric';
        $rules['ausverkauft'] = 'in:0,1';
        $rules['geloescht']   = 'in:0,1';

        $this->registerValidationRules($rules);

        $this->registerIncludes([
            'projekt' => [
                'key'      => 'projekt',
                'resource' => ProjectResource::class,
                'columns'  => [
                    'p.id',
                    'p.name',
                    'p.abkuerzung',
                    'p.beschreibung',
                    'p.farbe',
                ],
            ],
            'verkaufspreise' => [
                'key' => 'verkaufspreise',
                'filter' => [
                    ['property' => 'artikel', 'value' => ':id'],
                ],
                'sort' => ['menge' => 'ASC'],
                'resource' => SalesPriceResource::class,
            ],
            'dateien' => [
                'key' => 'dateien',
                'filter' => [
                    ['property' => 'artikel', 'value' => ':id'],
                ],
                'resource' => ArticleFileResource::class,
            ],
            'lagerbestand' => [
                /**
                 * Sonderfall
                 *
                 * @see ArticleResource::integrateIncludes
                 */
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function integrateIncludes(array $includes, array &$items, $isCollection = true)
    {
        // Ausnahme für "lagerbestand"-Include
        $lagerbestandIncludeKey = array_search('lagerbestand', $includes, true);
        if ($lagerbestandIncludeKey !== false) {

            // Mehrere Artikel
            if ($isCollection) {
                foreach ($items as &$item) {
                    $articleId = $item['id'];
                    $istLagerartikel = (int)$item['lagerartikel'] === 1;
                    $item['lagerbestand'] =
                        $istLagerartikel
                            ? $this->legacyApi->app->erp->ArtikelAnzahlVerkaufbar($articleId, 0, 0, 0, 0, true)
                            : [];
                }
                unset($item);
            }

            // Einzelner Artikel
            if (!$isCollection) {
                $articleId = $items['id'];
                $istLagerartikel = (int)$items['lagerartikel'] === 1;
                $items['lagerbestand'] =
                    $istLagerartikel
                        ? $this->legacyApi->app->erp->ArtikelAnzahlVerkaufbar($articleId, 0, 0, 0, 0, true)
                        : [];
            }

            unset($includes[$lagerbestandIncludeKey]);
        }

        // Andere Includes normal ausführen
        return parent::integrateIncludes($includes, $items, $isCollection);
    }

    /**
     * @return SelectQuery
     */
    protected function selectAllQuery()
    {
        return $this->db
            ->select()
            ->cols([
                //'a.*',
                'a.id',
                'a.typ',
                'a.nummer',
                'a.checksum',
                'a.projekt',
                'a.inaktiv',
                'a.ausverkauft',
                'a.warengruppe',
                'a.name_de',
                'a.name_en',
                'a.kurztext_de',
                'a.kurztext_en',
                'a.beschreibung_de',
                'a.beschreibung_en',
                'a.uebersicht_de',
                'a.uebersicht_en',
                'a.links_de',
                'a.links_en',
                'a.startseite_de',
                'a.startseite_en',
                'a.standardbild',
                'a.herstellerlink',
                'a.hersteller',
                'a.teilbar',
                'a.nteile',
                'a.seriennummern',
                'a.lager_platz',
                'a.lieferzeit',
                'a.lieferzeitmanuell',
                'a.sonstiges',
                'a.gewicht',
                'a.endmontage',
                'a.funktionstest',
                'a.artikelcheckliste',
                'a.stueckliste',
                'a.juststueckliste',
                'a.barcode',
                'a.hinzugefuegt',
                'a.pcbdecal',
                'a.lagerartikel',
                'a.porto',
                'a.chargenverwaltung',
                'a.provisionsartikel',
                'a.gesperrt',
                'a.sperrgrund',
                'a.geloescht',
                'a.gueltigbis',
                'a.umsatzsteuer',
                'a.klasse',
                'a.adresse',
                'a.shopartikel',
                'a.unishopartikel',
                'a.journalshopartikel',
                'a.katalog',
                'a.katalogtext_de',
                'a.katalogtext_en',
                'a.katalogbezeichnung_de',
                'a.katalogbezeichnung_en',
                'a.neu',
                'a.topseller',
                'a.startseite',
                'a.wichtig',
                'a.mindestlager',
                'a.mindestbestellung',
                'a.partnerprogramm_sperre',
                'a.internerkommentar',
                'a.intern_gesperrt',
                //'a.intern_gesperrtuser',
                'a.intern_gesperrtgrund',
                'a.inbearbeitung',
                //'a.inbearbeitunguser',
                'a.cache_lagerplatzinhaltmenge',
                'a.internkommentar',
                'a.firma',
                'a.logdatei',
                'a.anabregs_text',
                'a.autobestellung',
                'a.produktion',
                'a.herstellernummer',
                'a.restmenge',
                'a.mlmdirektpraemie',
                'a.keineeinzelartikelanzeigen',
                'a.mindesthaltbarkeitsdatum',
                'a.letzteseriennummer',
                'a.individualartikel',
                'a.keinrabatterlaubt',
                'a.rabatt',
                'a.rabatt_prozent',
                'a.geraet',
                'a.serviceartikel',
                'a.autoabgleicherlaubt',
                'a.pseudopreis',
                'a.freigabenotwendig',
                'a.freigaberegel',
                'a.nachbestellt',
                'a.ean',
                'a.mlmpunkte',
                'a.mlmbonuspunkte',
                'a.mlmkeinepunkteeigenkauf',
                //'a.shop', // Altlasten; wird zukünftig über artikel_shop gemacht
                //'a.shop2',
                //'a.shop3',
                //'a.usereditid',
                //'a.useredittimestamp',
                'a.einheit',
                'a.webid',
                'a.lieferzeitmanuell_en',
                'a.variante',
                'a.variante_von',
                'a.produktioninfo',
                'a.sonderaktion',
                'a.sonderaktion_en',
                'a.autolagerlampe',
                'a.leerfeld',
                'a.zolltarifnummer',
                'a.herkunftsland',
                'a.laenge',
                'a.breite',
                'a.hoehe',
                'a.gebuehr',
                'a.pseudolager',
                'a.downloadartikel',
                'a.matrixprodukt',
                'a.steuer_erloese_inland_normal',
                'a.steuer_aufwendung_inland_normal',
                'a.steuer_erloese_inland_ermaessigt',
                'a.steuer_aufwendung_inland_ermaessigt',
                'a.steuer_erloese_inland_steuerfrei',
                'a.steuer_aufwendung_inland_steuerfrei',
                'a.steuer_erloese_inland_innergemeinschaftlich',
                'a.steuer_aufwendung_inland_innergemeinschaftlich',
                'a.steuer_erloese_inland_eunormal',
                'a.steuer_erloese_inland_nichtsteuerbar',
                'a.steuer_erloese_inland_euermaessigt',
                'a.steuer_aufwendung_inland_nichtsteuerbar',
                'a.steuer_aufwendung_inland_eunormal',
                'a.steuer_aufwendung_inland_euermaessigt',
                'a.steuer_erloese_inland_export',
                'a.steuer_aufwendung_inland_import',
                'a.steuer_art_produkt',
                'a.steuer_art_produkt_download',
                'a.metadescription_de',
                'a.metadescription_en',
                'a.metakeywords_de',
                'a.metakeywords_en',
                'a.anabregs_text_en',
                'a.externeproduktion',
                'a.bildvorschau',
                'a.inventursperre',
                'a.variante_kopie',
                'a.unikat',
                'a.generierenummerbeioption',
                'a.allelieferanten',
                'a.tagespreise',
                'a.rohstoffe',
                'a.ohnepreisimpdf',
                'a.provisionssperre',
                'a.dienstleistung',
                'a.inventurekaktiv',
                'a.inventurek',
                'a.hinweis_einfuegen',
                'a.etikettautodruck',
                'a.lagerkorrekturwert',
                'a.autodrucketikett',
                'a.steuertext_innergemeinschaftlich',
                'a.steuertext_export',
                'a.formelmenge',
                'a.formelpreis',
                'a.ursprungsregion',
                'a.bestandalternativartikel',
                'a.metatitle_de',
                'a.metatitle_en',
                'a.vkmeldungunterdruecken',
                'a.altersfreigabe',
                'a.unikatbeikopie',
                'a.steuergruppe',
                'a.keinskonto',
                'a.berechneterek',
                'a.verwendeberechneterek',
                'a.berechneterekwaehrung',
                'a.artikelautokalkulation',
                'a.artikelabschliessenkalkulation',
                'a.artikelfifokalkulation',
                'a.freifeld1',
                'a.freifeld2',
                'a.freifeld3',
                'a.freifeld4',
                'a.freifeld5',
                'a.freifeld6',
                'a.freifeld7',
                'a.freifeld8',
                'a.freifeld9',
                'a.freifeld10',
                'a.freifeld11',
                'a.freifeld12',
                'a.freifeld13',
                'a.freifeld14',
                'a.freifeld15',
                'a.freifeld16',
                'a.freifeld17',
                'a.freifeld18',
                'a.freifeld19',
                'a.freifeld20',
                'a.freifeld21',
                'a.freifeld22',
                'a.freifeld23',
                'a.freifeld24',
                'a.freifeld25',
                'a.freifeld26',
                'a.freifeld27',
                'a.freifeld28',
                'a.freifeld29',
                'a.freifeld30',
                'a.freifeld31',
                'a.freifeld32',
                'a.freifeld33',
                'a.freifeld34',
                'a.freifeld35',
                'a.freifeld36',
                'a.freifeld37',
                'a.freifeld38',
                'a.freifeld39',
                'a.freifeld40',
            ])
            ->from(self::TABLE_NAME . ' AS a')
            ->where('a.geloescht <> 1');
    }

    /**
     * @return SelectQuery
     */
    protected function selectOneQuery()
    {
        return $this->selectAllQuery()->where('a.id = :id');
    }

    /**
     * @return SelectQuery
     */
    protected function selectIdsQuery()
    {
        return $this->selectAllQuery()->where('a.id IN (:ids)');
    }

    /**
     * @return false
     */
    protected function insertQuery()
    {
        return false;
    }

    /**
     * @return UpdateQuery
     */
    protected function updateQuery()
    {
        return $this->db->update()->table(self::TABLE_NAME)->where('id = :id');
    }

    /**
     * @return false
     */
    protected function deleteQuery()
    {
        return false;
    }
}
