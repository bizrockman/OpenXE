<?php

namespace Xentral\Modules\Api\Resource;

use Xentral\Components\Database\SqlQuery\SelectQuery;

/**
 * Einkaufspreise (nur lesend).
 *
 * Beispiel aktuelle Preise eines Lieferanten:
 *   GET /v1/einkaufspreise?adresse=15&gueltig_bis=0000-00-00
 */
class PurchasePriceResource extends AbstractResource
{
    const TABLE_NAME = 'einkaufspreise';

    protected function configure()
    {
        $this->setTableName(self::TABLE_NAME);

        $this->registerFilterParams([
            'artikel' => 'ep.artikel =',
            'adresse' => 'ep.adresse =',
            'projekt' => 'ep.projekt =',
            'waehrung' => 'ep.waehrung',
            'gueltig_bis' => 'ep.gueltig_bis =',
            'bestellnummer' => 'ep.bestellnummer %LIKE%',
            'bestellnummer_equals' => 'ep.bestellnummer LIKE',
            'firma' => 'ep.firma =',
        ]);

        $this->registerSortingParams([
            'artikel' => 'ep.artikel',
            'preis' => 'ep.preis',
            'menge' => 'ep.ab_menge',
            'gueltig_bis' => 'ep.gueltig_bis',
        ]);
    }

    /**
     * @return SelectQuery
     */
    protected function selectAllQuery()
    {
        return $this->db
            ->select()
            ->cols([
                'ep.id',
                'ep.artikel',
                'ep.adresse',
                'ep.projekt',
                'ep.preis',
                'ep.waehrung',
                'ep.ab_menge',
                'ep.vpe',
                'ep.preis_anfrage_vom',
                'ep.gueltig_bis',
                'ep.bestellnummer',
                'ep.bezeichnunglieferant',
                'ep.standard',
                'ep.rahmenvertrag',
                'ep.nichtberechnet',
                'ep.firma',
                'ep.logdatei',
            ])
            ->from(self::TABLE_NAME . ' AS ep')
            ->where('ep.geloescht <> 1');
    }

    /**
     * @return SelectQuery
     */
    protected function selectOneQuery()
    {
        return $this->selectAllQuery()->where('ep.id = :id');
    }

    /**
     * @return SelectQuery
     */
    protected function selectIdsQuery()
    {
        return $this->selectAllQuery()->where('ep.id IN (:ids)');
    }

    /**
     * @return false
     */
    protected function insertQuery()
    {
        return false;
    }

    /**
     * @return false
     */
    protected function updateQuery()
    {
        return false;
    }

    /**
     * @return false
     */
    protected function deleteQuery()
    {
        return false;
    }
}
