<?php
/**
 * Nc2ToNc3FaqBehavior
 *
 * @copyright Copyright 2017, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');

/**
 * Nc2ToNc3FaqBehavior
 *
 */
class Nc2ToNc3LinkBehavior extends Nc2ToNc3BaseBehavior
{

    /**
     * Get Log argument.
     *
     * @param Model $model Model using this behavior.
     * @param array $nc2Link Array data of Nc2Link.
     * @return string Log argument
     */
    public function getLogArgument(Model $model, $nc2Link)
    {
        return $this->__getLogArgument($nc2Link);
    }

    /**
     * Generate Nc3LinkBlock data.
     *
     * Data sample
     * data[LinkBlock][name]:
     * data[LinkSetting][use_workflow]:0
     * data[Categories]:
     *
     * @param Model $model Model using this behavior.
     * @param array $frameMap FrameMap data.
     * @param array $nc2Linklist Nc2Linklist data.
     * @param array $nc2Categories Nc2Categories data.
     * @return array Nc3Link data.
     */
    public function generateNc3LinkBlockData(Model $model, $frameMap, $nc2Linklist, $nc2Categories)
    {
        /* @var $Nc2ToNc3Map Nc2ToNc3Map */
        $Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
        $nc2LinklistId = $nc2Linklist['Nc2Linklist']['linklist_id'];
        $mapIdList = $Nc2ToNc3Map->getMapIdList('Link', $nc2LinklistId);
        if ($mapIdList) {
            // 移行済み
            return [];
        }

        $nc2LinklistId = $nc2Linklist['Nc2Linklist']['linklist_id'];
        $linklistMap = $this->_getMap($nc2LinklistId);
        if ($linklistMap) {
            // 既存の場合
            return [];
        }

        /* @var $Nc2ToNc3User Nc2ToNc3User */
        $Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
        $data['Frame'] = [
            'id' => $frameMap['Frame']['id'],
        ];
        $data['Block'] = [
            'id' => '',
            'key' => '',
            'room_id' => $frameMap['Frame']['room_id'],
            'plugin_key' => 'links',
            'public_type' => 1,
        ];
        $data['LinkBlock'] = [
            'key' => Hash::get($linklistMap, ['Linklist', 'key']),
            'name' => $nc2Linklist['Nc2Linklist']['linklist_name'],
            'created_user' => $Nc2ToNc3User->getCreatedUser($nc2Linklist['Nc2Linklist']),
            'created' => $this->_convertDate($nc2Linklist['Nc2Linklist']['insert_time']),
        ];
        $data['LinkSetting'] = [
            'use_workflow' => '0',
        ];
        $data['Categories'] = $this->_generateNc3CategoryData($nc2Categories);

        // @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Topic.php#L388-L393
        $data['Topic'] = [
            'plugin_key' => 'links',
        ];

        return $data;
    }

    /**
     * Get Nc2 Model.
     *
     * @param array $nc2Categories Nc2Categories table name.
     * @return array Category model.
     */
    protected function _generateNc3CategoryData($nc2Categories)
    {
        $result = [];
        foreach ($nc2Categories as $nc2Category) {
            $data = [
                'Category' => [
                    'id' => '',
                    'block_id' => '',
                    'key' => '',
                ],
                'CategoriesLanguage' => [
                    'id' => '',
                    'name' => $nc2Category['Nc2LinklistCategory']['category_name'],
                ],
                'CategoryOrder' => [
                    'id' => '',
                    'weight' => $nc2Category['Nc2LinklistCategory']['category_sequence'],
                    'block_key' => '',
                ],
            ];
            $result[] = $data;
        }
        return $result;
    }

    /**
     * Generate Nc3Link data.
     *
     * Data sample
     * data[Link][id]:
     * data[Link][key]:
     * data[Link][block_id]:
     * data[Link][status]:
     * data[Link][language_id]:
     * data[Link][category_id]:
     * data[Link][url]:
     * data[Link][title]:
     * data[Link][description]:
     * data[Link][click_count]:
     * data[LinkOrder][id]:
     * data[LinkOrder][block_key]:
     * data[LinkOrder][link_key]:
     * data[LinkOrder][category_key]:
     *
     * @param Model $model Model using this behavior.
     * @param array $nc3Link Link data.
     * @param array $nc2LinklistLink Nc2LinklistLink data.
     * @param array $nc2Categories Nc2LinkCategories data.
     * @return array Nc3Link data.
     */
    public function generateNc3LinkData(Model $model, $nc3Link, $nc2LinklistLink, $nc2Categories)
    {
        /* @var $Nc2ToNc3Map Nc2ToNc3Map */
        $Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
        $nc2LinkId = $nc2LinklistLink['Nc2LinklistLink']['link_id'];
        $mapIdList = $Nc2ToNc3Map->getMapIdList('LinkLink', $nc2LinkId);
        if ($mapIdList) {
            // 移行済み
            return [];
        }

        $data = [
            'Frame' => [
                'id' => $nc3Link['Frame']['id'],
            ],
            'Block' => [
                'id' => $nc3Link['Block']['id'],
                'key' => $nc3Link['Block']['key'],
            ],
            'Link' => [
                'id' => '',
                'key' => '',
                'block_id' => $nc3Link['Block']['id'],
                'status' => '1',
                'language_id' => $nc3Link['BlocksLanguage']['language_id'],
                'category_id' => '', // TODO カテゴリを設定する
                'url' => $nc2LinklistLink['Nc2LinklistLink']['url'],
                'title' => $nc2LinklistLink['Nc2LinklistLink']['title'],
                'description' => $nc2LinklistLink['Nc2LinklistLink']['description'],
                'click_count' => $nc2LinklistLink['Nc2LinklistLink']['view_count'],
            ],
            'LinkOrder' => [
                'id' => '',
                'block_key' => $nc3Link['Block']['key'],
                'link_key' => '',
                'category_key' => '',// TODO カテゴリ
            ],
        ];

        return $data;
    }

    /**
     * Generate Nc3Link data.
     *
     * Data sample
     * data[LinkFrameSetting][id]:
     * data[LinkFrameSetting][frame_key]:
     * data[LinkFrameSetting][display_type]:
     * data[LinkFrameSetting][category_separator_line]:
     * data[LinkFrameSetting][list_style]:
     * data[LinkFrameSetting][open_new_tab]:
     * data[LinkFrameSetting][display_click_count]:
     * data[LinkFrameSetting][created_user]:
     * data[LinkFrameSetting][created]:
     *
     * @param Model $model Model using this behavior.
     * @param array $nc2LinklistBlock NC2LinklistBlock data.
     * @return array Nc3LinkFrameSetting data.
     */
    public function generateNc3LinkFrameSettingData(Model $model, $nc2LinklistBlock)
    {
        /* @var $Nc2ToNc3Frame Nc2ToNc3Frame */
        /* @var $Nc2ToNc3Map Nc2ToNc3Map */
        $Nc2ToNc3Frame = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Frame');
        $Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
        $nc2BlockId = $nc2LinklistBlock['Nc2LinklistBlock']['block_id'];
        $mapIdList = $Nc2ToNc3Map->getMapIdList('LinkFrameSetting', $nc2BlockId);
        if ($mapIdList) {
            // 移行済み
            return [];
        }

        $frameMap = $Nc2ToNc3Frame->getMap($nc2BlockId);
        if (!$frameMap) {
            $message = __d('nc2_to_nc3', '%s does not migration.', $this->__getLogArgument($nc2LinklistBlock));
            $this->_writeMigrationLog($message);
            return [];
        }

        $nc2LinklistId = $nc2LinklistBlock['Nc2LinklistBlock']['linklist_id'];
        $LinkMap = $this->_getMap($nc2LinklistId);
        if ($LinkMap) {
            // 既存の場合
            return [];
        }

        // display_type更新
        $displayType = $nc2LinklistBlock['Nc2LinklistBlock']['display'];
        if ($displayType === LinkFrameSetting::TYPE_LIST_ONLY_TITLE
            && $nc2LinklistBlock['Nc2LinklistBlock']['has_description'] === '1'
        ) {
            $displayType = LinkFrameSetting::TYPE_LIST_WITH_DESCRIPTION;
        }
        $listStyle = $nc2LinklistBlock['Nc2LinklistBlock']['mark'];
        if ($listStyle === 'none') {
            $listStyle = '';
        }

        /* @var $Nc2ToNc3User Nc2ToNc3User */
        $Nc2ToNc3User = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3User');
        $data['LinkFrameSetting'] = [
            'id' => '',
            'frame_key' => $frameMap['Frame']['key'],
            'display_type' => $displayType,
            'category_separator_line' => $nc2LinklistBlock['Nc2LinklistBlock']['line'],
            'list_style' => $listStyle,
            'open_new_tab' => $nc2LinklistBlock['Nc2LinklistBlock']['target_blank_flag'],
            'display_click_count' => $nc2LinklistBlock['Nc2LinklistBlock']['view_count_flag'],
            'created_user' => $Nc2ToNc3User->getCreatedUser($nc2LinklistBlock['Nc2LinklistBlock']),
            'created' => $this->_convertDate($nc2LinklistBlock['Nc2LinklistBlock']['insert_time']),
        ];

        // @see https://github.com/NetCommons3/Topics/blob/3.1.0/Model/Topic.php#L388-L393
        $data['Topic'] = [
            'plugin_key' => 'Links',
        ];

        return $data;
    }

    /**
     * Get Log argument.
     *
     * @param array $nc2LinkBlock Array data of Nc2LinkBlock and Nc2Link.
     * @return string Log argument
     */
    private function __getLogArgument($nc2LinkBlock)
    {

        if (isset($nc2LinkBlock['Nc2LinkBlock'])) {
            return 'Nc2LinkBlock ' .
                'block_id:' . $nc2LinkBlock['Nc2LinkBlock']['block_id'];
        }

        return 'Nc2Link ' .
            'link_id:' . $nc2LinkBlock['Nc2Link']['link_id'] . ',' .
            'link_name:' . $nc2LinkBlock['Nc2Link']['link_name'];
    }

    /**
     * Get map
     *
     * @param array|string $nc2LinkIds Nc2CLink link_id.
     * @return array Map data with Nc2Block block_id as key.
     */
    protected function _getMap($nc2LinklistIds)
    {
        /* @var $Nc2ToNc3Map Nc2ToNc3Map */
        /* @var $Link Link */
        $Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
        $Link = ClassRegistry::init('Links.Link');

        $mapIdList = $Nc2ToNc3Map->getMapIdList('Link', $nc2LinklistIds);
        $query = [
            'fields' => [
                'Link.id',
                'Link.key',
            ],
            'conditions' => [
                'Link.id' => $mapIdList,
            ],
            'recursive' => -1,
            'callbacks' => false,
        ];
        $nc3Links = $Link->find('all', $query);
        if (!$nc3Links) {
            return $nc3Links;
        }

        $map = [];
        foreach ($nc3Links as $nc3Link) {
            $nc2Id = array_search($nc3Link['Link']['id'], $mapIdList);
            $map[$nc2Id] = $nc3Link;
        }

        if (is_string($nc2LinklistIds)) {
            $map = $map[$nc2LinklistIds];
        }

        return $map;
    }
}
