<?php
namespace wcf\system\worker;
use wcf\data\conversation\message\ConversationMessageEditor;
use wcf\data\conversation\message\ConversationMessageList;
use wcf\data\object\type\ObjectTypeCache;
use wcf\system\message\embedded\object\MessageEmbeddedObjectManager;
use wcf\system\search\SearchIndexManager;
use wcf\system\WCF;

/**
 * Worker implementation for updating conversation messages.
 * 
 * @author	Marcel Werk
 * @copyright	2001-2015 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	system.worker
 * @category	Community Framework
 */
class ConversationMessageRebuildDataWorker extends AbstractRebuildDataWorker {
	/**
	 * @see	\wcf\system\worker\AbstractWorker::$limit
	 */
	protected $limit = 500;
	
	/**
	 * @see	\wcf\system\worker\IWorker::countObjects()
	 */
	public function countObjects() {
		if ($this->count === null) {
			$this->count = 0;
			$sql = "SELECT	MAX(messageID) AS messageID
				FROM	wcf".WCF_N."_conversation_message";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute();
			$row = $statement->fetchArray();
			if ($row !== false) $this->count = $row['messageID'];
		}
	}
	
	/**
	 * @see	\wcf\system\worker\AbstractRebuildDataWorker::initObjectList
	 */
	protected function initObjectList() {
		$this->objectList = new ConversationMessageList();
		$this->objectList->sqlOrderBy = 'conversation_message.messageID';
		$this->objectList->sqlSelects = 'conversation.subject';
		$this->objectList->sqlJoins = 'LEFT JOIN wcf'.WCF_N.'_conversation conversation ON (conversation.firstMessageID = conversation_message.messageID)';
	}
	
	/**
	 * @see	\wcf\system\worker\IWorker::execute()
	 */
	public function execute() {
		$this->objectList->getConditionBuilder()->add('conversation_message.messageID BETWEEN ? AND ?', array($this->limit * $this->loopCount + 1, $this->limit * $this->loopCount + $this->limit));
		
		parent::execute();
		
		if (!$this->loopCount) {
			// reset search index
			SearchIndexManager::getInstance()->reset('com.woltlab.wcf.conversation.message');
		}
		
		// prepare statements
		$attachmentObjectType = ObjectTypeCache::getInstance()->getObjectTypeByName('com.woltlab.wcf.attachment.objectType', 'com.woltlab.wcf.conversation.message');
		$sql = "SELECT		COUNT(*) AS attachments
			FROM		wcf".WCF_N."_attachment
			WHERE		objectTypeID = ?
					AND objectID = ?";
		$attachmentStatement = WCF::getDB()->prepareStatement($sql);
		
		foreach ($this->objectList as $message) {
			SearchIndexManager::getInstance()->add('com.woltlab.wcf.conversation.message', $message->messageID, $message->message, ($message->subject ?: ''), $message->time, $message->userID, $message->username);
			
			$editor = new ConversationMessageEditor($message);
			$data = array();
			
			// count attachments
			$attachmentStatement->execute(array($attachmentObjectType->objectTypeID, $message->messageID));
			$row = $attachmentStatement->fetchSingleRow();
			$data['attachments'] = $row['attachments'];
			
			// update embedded objects
			$data['hasEmbeddedObjects'] = (MessageEmbeddedObjectManager::getInstance()->registerObjects('com.woltlab.wcf.conversation.message', $message->messageID, $message->message) ? 1 : 0);
			
			$editor->update($data);
		}
	}
}
