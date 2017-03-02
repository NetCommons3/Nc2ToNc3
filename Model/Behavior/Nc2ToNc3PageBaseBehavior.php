<?php
/**
 * Nc2ToNc3PageBaseBehavior
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('Nc2ToNc3BaseBehavior', 'Nc2ToNc3.Model/Behavior');
App::uses('CakeRoute', 'Routing/Route');
App::uses('PageContainer', 'Pages.Model');

/**
 * Nc2ToNc3PageBaseBehavior
 *
 */
class Nc2ToNc3PageBaseBehavior extends Nc2ToNc3BaseBehavior {

/**
 * CakeRouter class to parseNc2Page permalink.
 *
 * @var array
 */
	private $__CakeRouter = null;

/**
 * Nc2Page.id to Box map data
 *
 * @var array
 */
	private $__notMainPageIdToBoxMap = null;

/**
 * Convert Nc2Page permalink.
 *
 * @param Model $model Model using this behavior.
 * @param strine $nc2Permalink Nc2Item data.
 * @return string Converted Nc2Page permalink.
 */
	public function convertPermalink(Model $model, $nc2Permalink) {
		return $this->_convertPermalink($nc2Permalink);
	}

/**
 * Get map
 *
 * @param array|string $nc2PageIds Nc2Page page_id.
 * @return array Map data with Nc2Page page_id as key.
 */
	protected function _getMap($nc2PageIds) {
		if (!$this->__notMainPageIdToBoxMap) {
			$this->__setNotMainPageIdToBoxMap();
		}

		$nc2PageIdsForDiff = $nc2PageIds;
		if (!is_array($nc2PageIdsForDiff)) {
			$nc2PageIdsForDiff = [$nc2PageIdsForDiff];
		}

		$nc2NotMainPageIds = array_keys($this->__notMainPageIdToBoxMap);
		$nc2MainPageIds = array_diff($nc2PageIdsForDiff, $nc2NotMainPageIds);

		/* @var $Nc2ToNc3Map Nc2ToNc3Map */
		/* @var $BoxesPageContainer BoxesPageContainer */
		// Nc3Box.page_idはNullがあったため、Nc3BoxesPageContainerから取得
		$Nc2ToNc3Map = ClassRegistry::init('Nc2ToNc3.Nc2ToNc3Map');
		$BoxesPageContainer = ClassRegistry::init('Boxes.BoxesPageContainer');

		$mapIdList = $Nc2ToNc3Map->getMapIdList('Page', $nc2MainPageIds);
		$query = [
			'fields' => [
				'Page.id',
				'Box.id',
				'Box.room_id',
			],
			'conditions' => [
				'BoxesPageContainer.page_id' => $mapIdList,
				'BoxesPageContainer.container_type' => PageContainer::TYPE_MAIN,
				'Box.type' => Box::TYPE_WITH_PAGE
			],
			'recursive' => 0
		];
		$boxPageContainers = $BoxesPageContainer->find('all', $query);

		$map = [];
		foreach ($boxPageContainers as $boxPageContainer) {
			$nc2Id = array_search($boxPageContainer['Page']['id'], $mapIdList);
			$map[$nc2Id] = $boxPageContainer;
		}

		$nc2NotMainPageIds = array_diff($nc2PageIdsForDiff, $nc2MainPageIds);
		foreach ($nc2NotMainPageIds as $nc2NotMainPageId) {
			$map[$nc2NotMainPageId] = $this->__notMainPageIdToBoxMap[$nc2NotMainPageId];
		}

		if (!$map) {
			return $map;
		}

		if (is_string($nc2PageIds)) {
			$map = $map[$nc2PageIds];
		}

		return $map;
	}

/**
 * Convert Nc2Page permalink.
 *
 * @param strine $nc2Permalink Nc2Item data.
 * @return string Converted Nc2Page permalink.
 */
	protected function _convertPermalink($nc2Permalink) {
		// Pages.Routing/Route/SlugRoute の処理でRoutingされれなくなるため、変換しとく
		// @see https://github.com/NetCommons3/Pages/commit/b5740eb9923b50984760ce888d1d47501617f6d6#diff-7fdbf96769d3865d4e321fd323637187

		if (!$this->__CakeRouter) {
			$this->__CakeRouter = new CakeRoute('/*');
		}

		$url = '/' . $nc2Permalink;
		$route = $this->__CakeRouter->parse($url);
		if (!$route['pass']) {
			return $nc2Permalink;
		}

		// この処理で良いのか？
		$Space = ClassRegistry::init('Rooms.Space');
		$query = [
			'conditions' => [
				'permalink' => $route['pass'][0]
			],
			'recursive' => -1
		];
		if ($Space->find('count', $query)) {
			unset($route['pass'][0]);
		}

		return implode('/', $route['pass']);
	}

/**
 * Set not main Nc2Page.id to Box map data.
 *
 * @return void.
 */
	private function __setNotMainPageIdToBoxMap() {
		$confToContainerMap = [
			'headercolumn_page_id' => PageContainer::TYPE_HEADER,
			'leftcolumn_page_id' => PageContainer::TYPE_MAJOR,
			'rightcolumn_page_id' => PageContainer::TYPE_MINOR,
		];

		/* @var $Nc2Config AppModel */
		$Nc2Config = $this->_getNc2Model('config');
		$nc2Configs = $Nc2Config->findAllByConfName(
			array_keys($confToContainerMap),
			[
				'conf_name',
				'conf_value',
			],
			null,
			null,
			null,
			-1
		);

		/* @var $Box Box */
		// 対応候補のNc3Boxデータを取得
		// Page.idはuniqueにならないので、設定しない。
		$Box = ClassRegistry::init('Boxes.Box');
		$query = [
			'fields' => [
				'Box.id',
				'Box.space_id',
				'Box.room_id',
				'Box.container_type',
			],
			'conditions' => [
				'Box.type' => [
					Box::TYPE_WITH_SITE,
					Box::TYPE_WITH_SPACE
				],
				'Box.space_id !=' => Space::PUBLIC_SPACE_ID,
			],
			'recursive' => -1
		];
		$nc3Boxes = $Box->find('all', $query);

		// Nc2で、Public,Private,CommunityのNc3Boxにあたるデータを保持している
		// @see https://github.com/netcommons/NetCommons2/blob/2.4.2.1/html/maple/filter/Filter_SetDefault.class.php#L172-L189
		// @see https://github.com/netcommons/NetCommons2/blob/2.4.2.1/html/maple/filter/Filter_SetDefault.class.php#L217

		// Publicはサイト全体のデータとして扱われる
		// @see https://github.com/NetCommons3/Rooms/blob/3.1.0/Config/Migration/1479455827_switch_boxes.php#L343-L359
		// @see https://github.com/NetCommons3/Boxes/blob/3.1.0/Config/Migration/1477374165_switch_boxes.php#L49-L65
		foreach ($nc2Configs as $nc2Config) {
			$nc2PageIds = explode('|', $nc2Config['Nc2Config']['conf_value']);
			$nc2PublicPageId = $nc2PageIds[0];
			$nc2PrivatePageId = $nc2PageIds[1];
			$nc2CommunityPageId = $nc2PageIds[2];

			$nc2ConfName = $nc2Config['Nc2Config']['conf_name'];
			$nc3ContainerType = $confToContainerMap[$nc2ConfName];

			$path = '{n}.Box' .
				'[space_id=' . Space::WHOLE_SITE_ID . ']' .
				'[container_type=' . $nc3ContainerType . ']';
			$nc3Box = Hash::extract($nc3Boxes, $path);
			$this->__notMainPageIdToBoxMap[$nc2PublicPageId] = [
				'Box' => [
					'id' => $nc3Box[0]['id'],
					'room_id' => $nc3Box[0]['room_id'],
				]
			];

			$path = '{n}.Box' .
				'[space_id=' . Space::PRIVATE_SPACE_ID . ']' .
				'[container_type=' . $nc3ContainerType . ']';
			$nc3Box = Hash::extract($nc3Boxes, $path);
			$this->__notMainPageIdToBoxMap[$nc2PrivatePageId] = [
				'Box' => [
					'id' => $nc3Box[0]['id'],
					'room_id' => $nc3Box[0]['room_id'],
				]
			];

			$path = '{n}.Box' .
				'[space_id=' . Space::COMMUNITY_SPACE_ID . ']' .
				'[container_type=' . $nc3ContainerType . ']';
			$nc3Box = Hash::extract($nc3Boxes, $path);
			$this->__notMainPageIdToBoxMap[$nc2CommunityPageId] = [
				'Box' => [
					'id' => $nc3Box[0]['id'],
					'room_id' => $nc3Box[0]['room_id'],
				]
			];
		}
	}

}
