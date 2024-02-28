<?php

namespace app\modules\itrack\components;

use yii\db\Query;
use yii\rbac\DbManager;
use yii\rbac\Item;
use yii\rbac\Permission;
use yii\rbac\Role;

class ItrackDbManager extends DbManager
{
    
    public function getAssignments($userId)
    {
        if (empty($userId)) {
            return [];
        }
        
        $query = (new Query)
            ->from($this->assignmentTable)
            ->where(['user_id' => (string)$userId]);
        
        
        $all_data = $this->db->cache(function ($db) use ($query) {
            return $query->all($db);
        }, 360);
        
        
        $assignments = [];
        foreach ($all_data as $row) {
            $assignments[$row['item_name']] = new \yii\rbac\Assignment([
                'userId'    => $row['user_id'],
                'roleName'  => $row['item_name'],
                'createdAt' => $row['created_at'],
            ]);
        }
        
        return $assignments;
    }
    
    public function loadFromCache()
    {
        if ($this->items !== null || !$this->cache instanceof Cache) {
            return;
        }
        
        $data = $this->cache->get($this->cacheKey);
        if (is_array($data) && isset($data[0], $data[1], $data[2])) {
            list ($this->items, $this->rules, $this->parents) = $data;
            
            return;
        }
        
        $query = (new Query)->from($this->itemTable);
        $this->items = [];
        foreach ($query->all($this->db) as $row) {
            $this->items[$row['name']] = $this->populateItem($row);
        }
        
        $query = (new Query)->from($this->ruleTable);
        $this->rules = [];
        foreach ($query->all($this->db) as $row) {
            $this->rules[$row['name']] = json_decode($row['data']);
        }
        
        $query = (new Query)->from($this->itemChildTable);
        $this->parents = [];
        foreach ($query->all($this->db) as $row) {
            if (isset($this->items[$row['child']])) {
                $this->parents[$row['child']][] = $row['parent'];
            }
        }
        
        $this->cache->set($this->cacheKey, [$this->items, $this->rules, $this->parents]);
    }
    
    protected function getItems($type)
    {
        $query = (new Query())
            ->from($this->itemTable)
            ->where(['type' => $type])->orderBy('description');
        
        $items = [];
        foreach ($query->all($this->db) as $row) {
            $items[$row['name']] = $this->populateItem($row);
        }
        
        return $items;
    }
    
    protected function getItem($name)
    {
        if (empty($name)) {
            return null;
        }
        
        if (!empty($this->items[$name])) {
            return $this->items[$name];
        }
        
        $row = (new Query)->from($this->itemTable)
            ->where(['name' => $name])
            ->one($this->db);
        
        if ($row === false) {
            return null;
        }
        
        if (!isset($row['data']) || ($data = @json_decode($row['data'])) === false) {
            $row['data'] = null;
        }
        
        return $this->populateItem($row);
    }
    
    protected function addItem($item)
    {
        $time = time();
        if ($item->createdAt === null) {
            $item->createdAt = $time;
        }
        if ($item->updatedAt === null) {
            $item->updatedAt = $time;
        }
        $this->db->createCommand()
            ->insert($this->itemTable, [
                'name'        => $item->name,
                'type'        => $item->type,
                'description' => $item->description,
                'rule_name'   => $item->ruleName,
                'data'        => $item->data === null ? null : json_encode($item->data),
                'created_at'  => $item->createdAt,
                'updated_at'  => $item->updatedAt,
            ])->execute();
        
        $this->invalidateCache();
        
        return true;
    }
    
    /**
     * @inheritdoc
     */
    protected function updateItem($name, $item)
    {
        if ($item->name !== $name && !$this->supportsCascadeUpdate()) {
            $this->db->createCommand()
                ->update($this->itemChildTable, ['parent' => $item->name], ['parent' => $name])
                ->execute();
            $this->db->createCommand()
                ->update($this->itemChildTable, ['child' => $item->name], ['child' => $name])
                ->execute();
            $this->db->createCommand()
                ->update($this->assignmentTable, ['item_name' => $item->name], ['item_name' => $name])
                ->execute();
        }
        
        $item->updatedAt = time();
        
        $this->db->createCommand()
            ->update($this->itemTable, [
                'name'        => $item->name,
                'description' => $item->description,
                'rule_name'   => $item->ruleName,
                'data'        => $item->data === null ? null : json_encode($item->data),
                'updated_at'  => $item->updatedAt,
            ], [
                'name' => $name,
            ])->execute();
        
        $this->invalidateCache();
        
        return true;
    }
    
    /**
     * @inheritdoc
     */
    protected function addRule($rule)
    {
        $time = time();
        if ($rule->createdAt === null) {
            $rule->createdAt = $time;
        }
        if ($rule->updatedAt === null) {
            $rule->updatedAt = $time;
        }
        $this->db->createCommand()
            ->insert($this->ruleTable, [
                'name'       => $rule->name,
                'data'       => json_encode($rule),
                'created_at' => $rule->createdAt,
                'updated_at' => $rule->updatedAt,
            ])->execute();
        
        $this->invalidateCache();
        
        return true;
    }
    
    /**
     * @inheritdoc
     */
    protected function updateRule($name, $rule)
    {
        if ($rule->name !== $name && !$this->supportsCascadeUpdate()) {
            $this->db->createCommand()
                ->update($this->itemTable, ['rule_name' => $rule->name], ['rule_name' => $name])
                ->execute();
        }
        
        $rule->updatedAt = time();
        
        $this->db->createCommand()
            ->update($this->ruleTable, [
                'name'       => $rule->name,
                'data'       => json_encode($rule),
                'updated_at' => $rule->updatedAt,
            ], [
                'name' => $name,
            ])->execute();
        
        $this->invalidateCache();
        
        return true;
    }
    
    protected function populateItem($row)
    {
        $class = $row['type'] == Item::TYPE_PERMISSION ? Permission::class : Role::class;
        
        if (!isset($row['data']) || ($data = @json_decode($row['data'])) === false) {
            $data = null;
        }
        
        return new $class([
            'name'        => $row['name'],
            'type'        => $row['type'],
            'description' => $row['description'],
            'ruleName'    => $row['rule_name'],
            'data'        => $data,
            'createdAt'   => $row['created_at'],
            'updatedAt'   => $row['updated_at'],
        ]);
    }
    
}
