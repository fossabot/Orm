<?php

namespace Tsukasa\Orm;

use Tsukasa\QueryBuilder\Expression;
use Tsukasa\QueryBuilder\Q\QAndNot;
use Tsukasa\QueryBuilder\Q\QOr;


/**
 * Class TreeQuerySet.
 */
class TreeQuerySet extends QuerySet
{
    protected $treeKey;

    /**
     * Named scope. Gets descendants for node.
     *
     * @param bool $includeSelf
     * @param int  $depth the depth
     *
     * @return \Tsukasa\Orm\QuerySet
     * @throws \Exception
     */
    public function descendants($includeSelf = false, $depth = null)
    {

        if ($includeSelf === false) {
            $this->filter([
                'lft__gt' => $this->getModel()->lft,
                'rgt__lt' => $this->getModel()->rgt,
                'root' => $this->getModel()->root,
            ])->order(['lft']);
        }
        else {
            $this->filter([
                'lft__gte' => $this->getModel()->lft,
                'rgt__lte' => $this->getModel()->rgt,
                'root' => $this->getModel()->root,
            ])->order(['lft']);
        }

        if ($depth !== null) {
            $this->filter([
                'level__lte' => $this->getModel()->level + $depth,
            ]);
        }

        return $this;
    }

    /**
     * Named scope. Gets children for node (direct descendants only).
     *
     * @param bool $includeSelf
     *
     * @return \Tsukasa\Orm\QuerySet
     * @throws \Exception
     */
    public function children($includeSelf = false)
    {
        return $this->descendants($includeSelf, 1);
    }

    /**
     * Named scope. Gets ancestors for node.
     *
     * @param bool $includeSelf
     * @param int  $depth the depth
     *
     * @return \Tsukasa\Orm\QuerySet
     * @throws \Exception
     */
    public function ancestors($includeSelf = false, $depth = null)
    {
        $qs = $this->filter([
            'lft__lte' => $this->getModel()->lft,
            'rgt__gte' => $this->getModel()->rgt,
            'root' => $this->getModel()->root,
        ])->order(['-lft']);

        if ($includeSelf === false) {
            $this->exclude([
                'pk' => $this->getModel()->pk,
            ]);
        }

        if ($depth !== null) {
            $qs = $qs->filter(['level__lte' => $this->getModel()->level - $depth]);
        }

        return $qs;
    }

    /**
     * @param bool $includeSelf
     *
     * @return \Tsukasa\Orm\QuerySet
     * @throws \Exception
     */
    public function parents($includeSelf = false, $depth = null)
    {
        return $this->ancestors($includeSelf, $depth);
    }

    /**
     * Named scope. Gets root node(s).
     *
     * @return \Tsukasa\Orm\QuerySet
     * @throws \Exception
     */
    public function roots()
    {
        return $this->filter(['lft' => 1]);
    }

    /**
     * Named scope. Gets parent of node.
     *
     * @return \Tsukasa\Orm\QuerySet
     * @throws \Exception
     */
    public function parent()
    {
        return $this->filter([
            'lft__lt' => $this->getModel()->lft,
            'rgt__gt' => $this->getModel()->rgt,
            'level' => $this->getModel()->level - 1,
            'root' => $this->getModel()->root,
        ]);
    }

    /**
     * Named scope. Gets previous sibling of node.
     *
     * @return \Tsukasa\Orm\QuerySet
     * @throws \Exception
     */
    public function prev()
    {
        return $this->filter([
            'rgt' => $this->getModel()->lft - 1,
            'root' => $this->getModel()->root,
        ]);
    }

    /**
     * Named scope. Gets next sibling of node.
     *
     * @return \Tsukasa\Orm\QuerySet
     * @throws \Exception
     */
    public function next()
    {
        return $this->filter([
            'lft' => $this->getModel()->rgt + 1,
            'root' => $this->getModel()->root,
        ]);
    }

    /**
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getLastRoot()
    {
        return ($max = $this->max('root')) ? $max + 1 : 1;
    }

    /**
     * @param string $key
     *
     * @return $this
     * @throws \Exception
     */
    public function asTree($key = 'items')
    {
        $this->asArray(true);

        $this->treeKey = $key;

        return $this->order(['root', 'lft']);
    }

    /**
     * {@inheritdoc}
     */
    public function all($filter = [])
    {
        if ($this->treeKey) {
            $this->asArray(true);
        }

        $data = parent::all($filter);

        return $this->treeKey ? $this->toHierarchy($data) : $data;
    }

    /**
     * Find broken branch with deleted roots
     * sql:
     * SELECT t.id FROM tbl t WHERE
     * t.parent_id IS NOT NULL AND t.root NOT IN (
     *      SELECT r.id FROM tbl r WHERE r.parent_id IS NULL
     * ).
     *
     * Example: root1[1,4], nested1[2,3] and next delete root1 via QuerySet
     * like this: Model::objects()->filter(['name' => 'root1'])->delete();
     *
     * Problem: we have nested1 with lft 2 and rgt 3 without root.
     * Need find it and delete.
     *
     * @param $table
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    protected function deleteBranchWithoutRoot($table)
    {
        $id_attr = $this->getModel()->getField('pk')->getAttributeName();
        $pid_attr = $this->getModel()->getField('parent')->getAttributeName();

        $subQuery = clone $this->getQueryBuilder();
        $subQuery->clear()->setTypeSelect()->from($table)->select('root')->where(new QOr(["{$pid_attr}__isnull" => true, $pid_attr => 0]));

        $query = clone $this->getQueryBuilder();
        $query->clear()->setTypeSelect()->select(['id' => $id_attr])->from($table)->where([
            new QAndNot([new QOr(["{$pid_attr}__isnull" => true, $pid_attr => 0])]),
            new QAndNot(['root__in' => $subQuery]),
        ]);

        $stmt = $this->getConnection()->query($query->toSQL());
        $ids = $stmt->fetchColumn();
        if ($ids && count($ids) > 0) {
            $deleteQuery = clone $this->getQueryBuilder();
            $deleteQuery->clear()->setTypeDelete()->from($table)->where([$id_attr.'__in' => $ids]);
            $this->getConnection()->query($deleteQuery->toSQL())->execute();
        }
    }

    /**
     * Find broken branch with deleted parent
     * sql:
     * SELECT t.id, t.lft, t.rgt, t.root FROM tbl t
     * WHERE t.parent_id NOT IN (SELECT r.id FROM tbl r).
     *
     * Example: root1[1,6], nested1[2,5], nested2[3,4] and next delete nested1 via QuerySet
     * like this: Model::objects()->filter(['name' => 'nested1'])->delete();
     *
     * Problem: we have nested2 with lft 3 and rgt 4 without parent node.
     * Need find it and delete.
     *
     * @param $table
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    protected function deleteBranchWithoutParent($table)
    {
        $id_attr = $this->getModel()->getField('pk')->getAttributeName();
        $pid_attr = $this->getModel()->getField('parent')->getAttributeName();

        /*
        $query = new Query([
            'select' => ['id', 'lft', 'rgt', 'root'],
            'from' => $table,
            'where' => new Expression($db->quoteColumnName('parent_id') . ' NOT IN (' . $subQuery->allSql() . ')')
        ]);
         */
        $subQuery = clone $this->getQueryBuilder();
        $subQuery->clear()->setTypeSelect()->select(['id' => $id_attr])->from($table);

        $query = clone $this->getQueryBuilder();
        $query->clear()->setTypeSelect()->select(['id' => $id_attr, 'lft', 'rgt', 'root'])->from($table)->where([
            new QAndNot([new QOr(["{$pid_attr}__isnull" => true, $pid_attr => 0])]),
            new QAndNot(["{$pid_attr}__in" => $subQuery]),
        ]);

        $rows = $this->getConnection()->query($query->toSQL())->fetchAll();
        foreach ($rows as $row) {
            $deleteQuery = clone $this->getQueryBuilder();
            $deleteQuery->clear()->setTypeDelete()->from($table)->where([
                'lft__gte' => $row['lft'],
                'rgt__lte' => $row['rgt'],
                'root' => $row['root'],
            ]);
            $this->getConnection()->query($deleteQuery->toSQL())->execute();
        }
    }

    /**
     * Find and delete broken branches without root, parent
     * and with incorrect lft, rgt.
     *
     * sql:
     * SELECT id, root, lft, rgt, (rgt-lft-1) AS move
     * FROM tbl t
     * WHERE NOT t.lft = (t.rgt-1)
     * AND NOT id IN (
     *      SELECT tc.parent_id
     *      FROM tbl tc
     *      WHERE tc.parent_id = t.id
     * )
     * ORDER BY rgt DESC
     */
    protected function rebuildLftRgt($table)
    {
//        $subQuery = "SELECT `tt`.`parent_id` FROM {$table} AS `tt` WHERE `tt`.`parent_id`=`t`.`id`";
//        $where = 'NOT `lft`=(`rgt`-1) AND NOT `id` IN ('.$subQuery.')';
//        $sql = 'SELECT `id`, `root`, `lft`, `rgt`, `rgt`-`lft`-1 AS `move` FROM '.$table.' AS `t` WHERE '.$where.' ORDER BY `rgt` ASC';
        $id_attr = $this->getModel()->getField('pk')->getAttributeName();
        $pid_attr = $this->getModel()->getField('parent')->getAttributeName();


        $sql = <<<SQL
SELECT `{$id_attr}` as id, `root`, `lft`, `rgt`, `rgt`-`lft`-1 AS `move` 
FROM {$table} AS `t` 
WHERE NOT `lft`=(`rgt`-1) AND NOT `{$id_attr}` IN (SELECT `tt`.`{$pid_attr}` FROM {$table} AS `tt` WHERE `tt`.`{$pid_attr}`=`t`.`{$id_attr}`) 
ORDER BY `rgt` ASC
SQL;

        $adapter = $this->getAdapter();

        $rows = $this->getConnection()->query($adapter->quoteSql($sql))->fetchAll();
        foreach ($rows as $row) {
            if ($row['move'] < 0) {
                Xcart::app()->logger->warning("Tree in table '{$table}', maybe broken and can't fix automaticly.", ['fixdata' => $row]);
                continue;
            }

            $sql = 'UPDATE '.$table.' SET `lft`=`lft`-'.$row['move'].', `rgt`=`rgt`-'.$row['move'].' WHERE `root`='.$row['root'].' AND `lft` > '.$row['rgt'];
            $this->getConnection()->query($sql)->execute();
            $sql = 'UPDATE '.$table.' SET `rgt`=`rgt`-'.$row['move'].' WHERE `root`='.$row['root'].' AND `lft`<`rgt` AND `rgt` >= '.$row['rgt'];
            $this->getConnection()->query($sql)->execute();
        }
    }

    /**
     * WARNING: Don't use QuerySet inside QuerySet in this
     * method because recursion...
     *
     * @throws \Exception
     */
    public function findAndFixCorruptedTree()
    {
        $model = $this->getModel();
        $table = $model->tableName();
        $this->deleteBranchWithoutRoot($table);
        $this->deleteBranchWithoutParent($table);
        $this->rebuildLftRgt($table);
    }

    /**
     * Пересчитываем дерево после удаления моделей через
     * $modelClass::objects()->filter(['pk__in' => $data])->delete();.
     *
     * @return int
     * @throws \Exception
     */
    public function delete()
    {
        $deleted = parent::delete();
        $this->findAndFixCorruptedTree();

        return $deleted;
    }

    /**
     * @param int   $key
     * @param int   $delta
     * @param int   $root
     * @param array $data
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    private function shiftLeftRight($key, $delta, $root, $data)
    {
        foreach (['lft', 'rgt'] as $attribute) {
            $this->filter([$attribute.'__gte' => $key, 'root' => $root])
                ->update([$attribute => new Expression($attribute.sprintf('%+d', $delta))]);

            foreach ($data as &$item) {
                if ($item[$attribute] >= $key) {
                    $item[$attribute] += $delta;
                }
            }
        }

        return $data;
    }

    /**
     * Make hierarchy array by level.
     *
     * @param $collection Model[]
     *
     * @return array
     */
    public function toHierarchy($collection)
    {
        // Trees mapped
        $trees = [];
        if (count($collection) > 0) {
            // Node Stack. Used to help building the hierarchy
            $stack = [];
            foreach ($collection as $item) {
                $item[$this->treeKey] = [];
                // Number of stack items
                $l = count($stack);
                // Check if we're dealing with different levels
                while ($l > 0 && $stack[$l - 1]['level'] >= $item['level']) {
                    array_pop($stack);
                    --$l;
                }
                // Stack is empty (we are inspecting the root)
                if ($l == 0) {
                    // Assigning the root node
                    $i = count($trees);
                    $trees[$i] = $item;
                    $stack[] = &$trees[$i];
                } else {
                    // Add node to parent
                    $i = count($stack[$l - 1][$this->treeKey]);
                    $stack[$l - 1][$this->treeKey][$i] = $item;
                    $stack[] = &$stack[$l - 1][$this->treeKey][$i];
                }
            }
        }

        return $trees;
    }
}
