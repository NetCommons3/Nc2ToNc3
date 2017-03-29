<?php
/**
 * Nc2ToNc3AppModel
 *
 * @copyright Copyright 2014, NetCommons Project
 * @author Kohei Teraguchi <kteraguchi@commonsnet.org>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

/**
 * Nc2ToNc3AppModel
 * トランザクションを開始(NetCommonsAppModel::begin)すると、
 * 以降のModelがmasterの設定で接続するため、Modelクラスを直接継承する。
 *   → AppModelを継承しないと、Model::actsAsプロパティがマージされないので要注意！
 * @see https://github.com/cakephp/cakephp/blob/2.9.6/lib/Cake/Model/Model.php#L749-L756
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 *
 */
class Nc2ToNc3AppModel extends Model {

}
