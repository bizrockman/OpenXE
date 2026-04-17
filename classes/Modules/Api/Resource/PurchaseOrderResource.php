<?php

namespace Xentral\Modules\Api\Resource;

use Xentral\Components\Database\SqlQuery\SelectQuery;

class PurchaseOrderResource extends AbstractResource
{
    const TABLE_NAME = 'bestellung';

    protected function configure()
    {
        $this->setTableName(self::TABLE_NAME);

        $this->registerFilterParams([
            'belegnr'           => 'b.belegnr =',
            'status'            => 'b.status =',
            'projekt'           => 'b.projekt =',
            'adresse'           => 'b.adresse =',
            'kundennummer'      => 'b.kundennummer =',
            'lieferantennummer' => 'b.lieferantennummer =',
            'datum_von'         => 'b.datum >=',
            'datum_bis'         => 'b.datum <=',
            'name'              => 'b.name %LIKE%',
        ]);

        $this->registerSortingParams([
            'datum'       => 'b.datum',
            'belegnr'     => 'b.belegnr',
            'status'      => 'b.status',
            'gesamtsumme' => 'b.gesamtsumme',
            'name'        => 'b.name',
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
                'b.id',
                'b.belegnr',
                'b.datum',
                'b.projekt',
                'b.status',
                'b.adresse',
                'b.name',
                'b.kundennummer',
                'b.lieferantennummer',
                'b.gesamtsumme',
                'b.waehrung',
                'b.versandart',
                'b.lieferdatum',
                'b.bestaetigteslieferdatum',
                'b.bearbeiter',
                'b.freitext',
                'b.internebemerkung',
                'b.zahlungsweise',
                'b.bestellungsart',
            ])
            ->from(self::TABLE_NAME . ' AS b');
    }

    /**
     * @return SelectQuery
     */
    protected function selectOneQuery()
    {
        return $this->selectAllQuery()->where('b.id = :id');
    }

    /**
     * @return SelectQuery
     */
    protected function selectIdsQuery()
    {
        return $this->selectAllQuery()->where('b.id IN (:ids)');
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
