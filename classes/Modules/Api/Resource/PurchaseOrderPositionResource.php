<?php

namespace Xentral\Modules\Api\Resource;

use Xentral\Components\Database\SqlQuery\SelectQuery;

class PurchaseOrderPositionResource extends AbstractResource
{
    const TABLE_NAME = 'bestellung_position';

    protected function configure()
    {
        $this->setTableName(self::TABLE_NAME);

        $this->registerFilterParams([
            'bestellung' => 'bp.bestellung =',
            'artikel'    => 'bp.artikel =',
            'status'     => 'bp.status =',
        ]);

        $this->registerSortingParams([
            'sort'         => 'bp.sort',
            'lieferdatum'  => 'bp.lieferdatum',
            'preis'        => 'bp.preis',
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
                'bp.id',
                'bp.bestellung',
                'bp.artikel',
                'bp.projekt',
                'bp.bezeichnunglieferant',
                'bp.bestellnummer',
                'bp.beschreibung',
                'bp.menge',
                'bp.preis',
                'bp.waehrung',
                'bp.lieferdatum',
                'bp.vpe',
                'bp.sort',
                'bp.status',
                'bp.geliefert',
                'bp.abgerechnet',
                'bp.abgeschlossen',
                'bp.einheit',
                'bp.zolltarifnummer',
                'bp.herkunftsland',
            ])
            ->from(self::TABLE_NAME . ' AS bp');
    }

    /**
     * @return SelectQuery
     */
    protected function selectOneQuery()
    {
        return $this->selectAllQuery()->where('bp.id = :id');
    }

    /**
     * @return SelectQuery
     */
    protected function selectIdsQuery()
    {
        return $this->selectAllQuery()->where('bp.id IN (:ids)');
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
