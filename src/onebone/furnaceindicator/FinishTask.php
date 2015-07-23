<?php 

namespace onebone\furnaceindicator;

use pocketmine\scheduler\PluginTask;

class FinishTask extends PluginTask{
	private $axis;
	
	public function __construct(FurnaceIndicator $plugin, $axis){
		parent::__construct($plugin);
		$this->axis = $axis;
	}
	
	public function onRun($currentTick){
		$this->getOwner()->finishSchedule($this->axis);
	}
}