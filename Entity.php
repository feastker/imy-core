<?

namespace Imy\Core;

use File;
use Imy\Core\DB;

use Imy\Core\User;

abstract class Entity
{
    protected $info;
    protected $model;
    protected $database = 'default';
    protected $defaultOrder = 'id';
    protected $defaultOrderDir = 'ASC';
    protected $seo;
    protected $seoEntity;
    protected $relation;
    protected $seoUrl = '';
    protected $sizes = '';
    public $file;
    protected $seoTitle = '[header]';
    private $seoFields = [
        'seo_title',
        'seo_alias',
        'seo_header',
        'seo_url',
        'seo_real_url',
        'seo_keywords',
        'seo_description',
        'seo_site'
    ];

    function __construct($data = false, $createIfNotExist = false, $withSeo = false, $order = false)
    {
        $this->model = M($this->entity, $this->database);

        if (!empty($data)) {
            if (is_numeric($data)) {
                $this->getById($data, $withSeo);
            } elseif (is_object($data)) {
                $this->info = $data;
            } elseif (is_array($data)) {
                $this->getBy($data, false, $order, $withSeo);
            }
        }

        if ($createIfNotExist && !$this->exist()) {
            $this->create($data ?? []);
        }

        if(!empty($this->info))
            $this->info->setPrimary($this->primary);


    }


    function get($key)
    {
        return $this->info->{$key};
    }

    function __isset($key)
    {
        return !empty($this->info->{$key}) ? true : false;
    }

    function __get($key)
    {
        return isset($this->info->{$key}) ? $this->info->{$key} : false;
    }

    function create($data)
    {
        $this->info = M($this->entity, $this->database)->factory();
        $id = $this->set($data);

        return $id;
    }

    function set($key, $val = false)
    {
        if (!is_array($key)) {
            $key = [
                $key => $val
            ];
        }
        if ($this->seo) {
            $seo = $this->saveSeo($key);
        }

        if (!empty($key)) {
            $this->info->setValues($key);
            $result = $this->info->save();

            if (!empty($seo) && empty($seo->get('entity_id'))) {
                $seo->set('entity_id', $this->info->id);
            }

            return $result;
        }

        return false;
    }

    function saveSeo(&$values)
    {
        $seoData = [];
        foreach ($values as $k => $v) {
            if (in_array($k, $this->seoFields)) {
                $seoData[str_replace('seo_', '', $k)] = $v;
                unset($values[$k]);
            }
        }

        if (!empty($seoData)) {
            $seoObject = new \Model\Seo([
                'entity' => $this->seoEntity,
                'entity_id' => $this->id
            ], true);

            if (empty($seoData['alias']) && !empty($seoData['header'])) {
                $seoData['alias'] = \Helper::transliterate($seoData['header']);
            }

            if (!empty($seoData['alias']))
                $seoData['url'] = $this->seoUrl .= $seoData['alias'];

            if (!empty($seoData['header'])) {
                $seoData['title'] = strtr($this->seoTitle, [
                    '[header]' => $seoData['header']
                ]);
            }

            $seoObject->set($seoData);
            return $seoObject;
        }
    }

    function setSeoUrl($url)
    {
        $this->seoUrl = $url;
    }

    function getBy($field, $value = false, $order = false, $withSeo = false)
    {

        $where = [];
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $where[$k] = $v;
            }
        } else {
            $where[$field] = $value;
        }

        $this->info = M($this->entity, $this->database)->get();
        $this->info->select($this->entity . '.*');

        if (!empty($withSeo) && $this->seo) {
            $this->info->select('seo.url', 'seo.header');
            $this->info = $this->info->join('seo seo', 'LEFT')
                ->on('entity_id', $this->entity . '.id')->on('entity', '"' . (empty($this->seoEntity) ? $this->entity : $this->seoEntity) . '"');
        }


        foreach ($where as $k => $v) {
            if (is_array($v) && (isset($v['value']) || isset($v['sign']))) {
                $this->info = $this->info->where($k, $v['value'], $v['sign']);
            } else {
                $this->info = $this->info->where($k, $v);
            }

        }

        if (!empty($order)) {
            $orders = explode(',', $order);
            foreach ($orders as $order) {
                $order = explode(' ', $order);
                $this->info = $this->info->orderBy($order[0], !empty($order[1]) ? $order[1] : 'ASC');
            }
        }

        $this->info = $this->info->fetch();

    }

    function count($field = [], $value = false, $callback = false)
    {
        $where = [];
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $where[$k] = $v;
            }
        } else {
            $where[$field] = $value;
        }


        $result = $this->model->get();
        $result->select('count(*) ct');

        foreach ($where as $k => $v) {
            if (is_array($v) && (isset($v['value']) || isset($v['sign']))) {
                $result = $result->where($k, $v['value'], $v['sign']);
            } else {
                $result = $result->where($k, $v);
            }

        }

        if ($callback) {
            $result = $callback($result);
        }

        return $result->fetch()->ct;
    }

    function getMany($field = [], $value = false, $order = false, $dir = 'ASC', $callback = false, $options = [])
    {

        $where = [];
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $where[$k] = $v;
            }
        } else {
            $where[$field] = $value;
        }


        $result = $this->model->get();
        $result->select($options['select'] ?? $this->entity . '.*');

        if (!empty($this->seo)) {
            $result->select('seo.url', 'seo.header');
            $result = $result->join('seo seo', 'LEFT')
                ->on('entity_id', $this->entity . '.id')->on('entity', '"' . (empty($this->seoEntity) ? $this->entity : $this->seoEntity) . '"');
        }

        foreach ($where as $k => $v) {
            if (is_array($v) && (isset($v['value']) || isset($v['sign']))) {
                $result = $result->where($k, $v['value'], $v['sign']);
            } else {
                $result = $result->where($k, $v);
            }

        }

        if ($callback) {
            $result = $callback($result);
        }

        if (!empty($order)) {
            $result = $result->orderBy($order, $dir);
        }

        if (!empty($options['limit']))
            $result->limit($options['limit']);

        if (!empty($options['offset']))
            $result->offset($options['offset']);

        $result = $result->fetchAll(!empty($options['die']));

        $class = get_class($this);

        $objects = [];
        foreach ($result as $item) {
            $objects[] = new $class($item);
        }

        return $objects;
    }

    function getById($id, $withSeo = false)
    {
        $this->getBy($this->entity . '.id', $id, false, $withSeo);
    }

    function delete()
    {

        if ($this->seo) {
            $seo = new \Model\Seo([
                'entity' => $this->seoEntity,
                'entity_id' => $this->id
            ]);
            if ($seo->exist())
                $seo->delete();
        }

        if (!empty($this->relation)) {
            foreach ($this->relation as $table => $keys) {
                if (!is_array($keys)) {
                    $keys = [$keys => 'id'];
                }

                $toDelete = M($table)->get();
                foreach ($keys as $key => $val) {
                    $toDelete->where($key, strpos($val, '"') === false ? $this->{$val} : $val);
                }
                $toDelete = $toDelete->fetch();

                if (!empty($toDelete))
                    $toDelete->delete();

            }
        }

        $this->info->delete();
    }

    function getInfo()
    {
        if (!empty($this->seo) && !empty($this->get('id'))) {
            $seoObject = new \Model\Seo([
                'entity' => $this->seoEntity,
                'entity_id' => $this->id
            ], true);
            $this->info->seo_header = $seoObject->header;
            $this->info->seo_alias = $seoObject->alias;
        }

        return $this->info;
    }

    function exist()
    {
        return !empty($this->info) ? true : false;
    }

    function error($msg)
    {
        return [
            'status' => 'error',
            'message' => $msg
        ];
    }

    function success($msg)
    {
        return [
            'status' => 'success',
            'message' => $msg
        ];
    }

    function reset()
    {
        $this->info = false;
    }

    function datatable($opts, $callback = false)
    {
        $items = M($this->entity, $this->database)->get();

        $ct = $items->copy()->count();

        $orderField = @$opts['columns'][$opts['order'][0]['column']]['data'] ?: $this->defaultOrder;
        $orderDir = @$opts['order'][0]['dir'] ?: $this->defaultOrderDir;

        if (!empty($opts['cond'])) {
            foreach ($opts['cond'] as $k => $v) {
                if (is_array($v) && (isset($v['value']) || isset($v['sign']))) {
                    $items = $items->where($k, $v['value'], $v['sign']);
                } else {
                    $items = $items->where($k, $v);
                }
            }
        }

        if (!empty($opts['filter'])) {
            foreach ($opts['filter'] as $k => $v) {
                if (is_array($v) && (isset($v['value']) || isset($v['sign']))) {
                    $items = $items->where($k, $v['value'] . ($v['sign'] == 'LIKE' ? '%' : ''), $v['sign']);
                } else {
                    $items = $items->where($k, $v);
                }
            }
        }

        if (!empty($this->seo)) {
            $items->select(
                $this->entity . '.*',
                'seo.url as seo_url',
                'seo.alias as seo_alias',
                'seo.header as seo_header',
                'seo.site as site'
            );
            $items->join('seo')
                ->on('seo.entity', '"' . $this->seoEntity . '"')
                ->on('seo.entity_id', $this->entity . '.id');
        }

        if (!empty($opts['search']) && !empty($opts['search']['value']) && !empty($this->filter)) {
            $items->whereOpen();
            foreach ($this->filter as $filter) {
                $items->orWhere($filter, '%' . $opts['search']['value'] . '%', 'LIKE');
            }
            $items->whereClose();
        }


        if ($callback) {
            $items = $callback($items);
        }

        $filtered = $items->copy()->count($this->entity . '.id');

        if (!empty($orderField)) {
            $items = $items->orderBy($this->entity . '.' . $orderField, strtoupper($orderDir));
        } else {
            $items = $items->orderBy($this->entity . '.id', 'DESC');
        }

        if (!empty($opts['start'])) {
            $items->offset($opts['start']);
        }

        if (!empty($opts['length'])) {
            $items->limit($opts['length']);
        }

        $items = $items->fetchAll();

        $return = [];

        $return['draw'] = $opts['draw'] ?: 1;
        $return['recordsTotal'] = $ct;
        $return['recordsFiltered'] = $filtered;
        $return['data'] = $items;

        return $return;
    }

    function getMaxPosition()
    {
        $max = M($this->entity)->get()->max('position');
        return $max;
    }

    function copy($dataChanged, $tree = []) {
        $model = clone $this;
        $model->getById($this->id);

        $newIdModel = $model->info->copy($dataChanged);

        foreach ($tree as $entity => $value) {
            if(!is_array($value)) {
                $results = M($entity)->get()->where($value,$this->id)->fetchAll();
                foreach($results as $result) {
                    $result->copy([$value => $newIdModel]);
                }
            }

            if(is_array($value)) {
                foreach ($value as $subEntity => $subData) {
                    $results = M($entity)->get()->where($subEntity,$this->id)->fetchAll();
                    foreach($results as $result) {
                        $idNewSubResult = $result->copy([$subEntity => $newIdModel]);

                        foreach ($subData as $subKey => $subVal) {
                            $subResults = M($subKey)->get()->where($subVal,$result->id)->fetchAll();
                            foreach ($subResults as $subResult) {
                                $subResult->copy([$subVal => $idNewSubResult]);
                            }
                        }
                    }
                }
            }
        }

        return $newIdModel;
    }

}