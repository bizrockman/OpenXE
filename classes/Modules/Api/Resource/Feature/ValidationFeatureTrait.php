<?php

namespace Xentral\Modules\Api\Resource\Feature;

use Rakit\Validation\Validation;
use Xentral\Modules\Api\Exception\ValidationErrorException;
use Xentral\Modules\Api\Resource\Exception\ValidationRequiredException;

trait ValidationFeatureTrait
{
    /** @var array $validationRules */
    private $validationRules;

    /** @var string $resourceTableName */
    private $resourceTableName;

    /**
     * Validierungsregeln festlegen
     *
     * @example $this->registerValidationRules([
     *              'id' => 'not_present',
     *              'bezeichnung' => 'required|unique:artikelkategorien,bezeichnung',
     *              'next_number' => 'numeric',
     *              'projekt' => 'numeric',
     *              'parent' => 'numeric',
     *              'externenummer' => 'numeric',
     *              'geloescht' => 'in:0,1',
     *          ]);

     * @see https://github.com/rakit/validation#available-rules
     *
     * @param array $rules
     */
    protected function registerValidationRules(array $rules)
    {
        $this->validationRules = $rules;
    }

    /**
     * @param array $inputVars
     * @param int   $selfId
     */
    protected function validateData($inputVars, $selfId = null)
    {
        if (empty($this->validationRules)) {
            throw new ValidationRequiredException();
        }

        // Regeln aufbereiten
        $rules = $this->validationRules;
        if ($selfId) {
            $needle = sprintf('unique:%s,', $this->resourceTableName);
            foreach ($rules as $ruleKey => $ruleVal) {
                if ($pos = strpos($ruleVal, $needle)) {

                    // Nach Anfang der nachfolgenden Regel suchen
                    $searchPos = $pos + strlen($needle);
                    $insertPos = strpos($ruleVal, '|', $searchPos);

                    // Keine weitere Regel gefunden; am Ende anfügen
                    if (!$insertPos) {
                        $insertPos = strlen($ruleVal);
                    }

                    // ID als dritten Parameter für UniqueRule übergeben
                    /** @see UniqueRule Parameter "except" */
                    $newRuleVal = substr_replace($ruleVal, ',' . $selfId, $insertPos, 0);

                    $rules[$ruleKey] = $newRuleVal;
                }
            }
        }

        /** @var Validation $validation */
        $validation = $this->validator->validate($inputVars, $rules);
        if ($validation->fails()) {
            throw new ValidationErrorException($validation->errors()->all());
        }
    }

    /**
     * Reduce input to keys that have a registered validation rule.
     *
     * Rakit/Validation does not reject unknown keys by default, so without
     * an extra filter step a client could send any extra field that happens
     * to match a real column on the table and have it written. This
     * tightens insert/update so only fields the resource has explicitly
     * declared in registerValidationRules() can ever reach the SQL builder.
     *
     * Identifier-injection through column names is already prevented by
     * Aura's backtick-quoting, but the column-itself-must-be-allowlisted
     * property is what application authors actually expect.
     *
     * @param array $inputVars
     *
     * @return array
     */
    protected function filterToValidatedKeys(array $inputVars)
    {
        if (empty($this->validationRules)) {
            return $inputVars;
        }
        return array_intersect_key($inputVars, $this->validationRules);
    }

    /**
     * @param string $tableName
     */
    protected function setTableName($tableName)
    {
        $this->resourceTableName = $tableName;
    }
}
