<?php

namespace onebone\furnaceindicator;

use pocketmine\block\Furnace as FurnaceBlock;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\inventory\FurnaceInventory;
use pocketmine\inventory\SimpleTransactionGroup;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;

class FurnaceIndicator extends PluginBase implements Listener{
	/**
	 * @var array
	 */
	private $remain; // 0: player name, 1: time left, 2: schedule id, 3: register time

	public function onEnable(){
		@mkdir($this->getDataFolder());
		if(!is_file($this->getDataFolder()."")){
			file_put_contents($this->getDataFolder()."remains.dat", serialize([]));
		}
		$this->remain = unserialize(file_get_contents($this->getDataFolder()."remains.dat"));

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onInventoryTransaction(InventoryTransactionEvent $event){
		$transaction = $event->getTransaction();
		if($transaction instanceof SimpleTransactionGroup){
			foreach($transaction->getTransactions() as $ts){
				if(($inv = $ts->getInventory()) instanceof FurnaceInventory){
					$furnace = $inv->getHolder();
					if(!isset($this->remain[$furnace->getX().":".$furnace->getY().":".$furnace->getZ().":".$furnace->getLevel()->getName()])){
						if($ts->getTargetItem()->getID() !== 0){
							$player = $transaction->getSource();
							$scheduleId = $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "finishSchedule"], [$furnace->getX().":".$furnace->getY().":".$furnace->getZ().":".$furnace->getLevel()->getName()]), 3600)->getTaskId();
							$this->remain[$furnace->getX().":".$furnace->getY().":".$furnace->getZ().":".$furnace->getLevel()->getName()] = [
								$player->getName(),
								3600,
								$scheduleId,
								time()
							];
							$player->sendMessage("[FurnaceIndicator] Your furnace will be protected for 3 minutes.");
						}
					}else{
						for($i = 0; $i < 3; $i++){
							if($i === $ts->getSlot()){
								if($ts->getTargetItem()->getID() === 0){
									goto unprotect;
								}
							}
							if($inv->getItem($i)->getID() == 0){
								unprotect:
								if($i === 2){
									$data = $this->remain[$furnace->getX().":".$furnace->getY().":".$furnace->getZ().":".$furnace->getLevel()->getName()];
									$this->getServer()->getScheduler()->cancelTask($data[2]);
									$transaction->getSource()->sendMessage("[FurnaceIndicator] Your furnace has been unprotected.");
									unset($this->remain[$furnace->getX().":".$furnace->getY().":".$furnace->getZ().":".$furnace->getLevel()->getName()]);
								}
							}else{
								break;
							}
						}
					}
				}
			}
		}
	}

	public function onDisable(){
		$now = time();
		foreach($this->remain as $key => $data){
			$this->remain[$key][1] = ($data[1] - $now + $data[3]);
			$this->getServer()->getScheduler()->cancelTask($data[2]);
		}

		file_put_contents($this->getDataFolder()."remains.dat", serialize($this->remain));
	}

	public function finishSchedule($location){
		if(isset($this->remain[$location])){
			if(($player = $this->getServer()->getPlayerExact($this->remain[$location][0]))){
				$player->sendMessage("[FurnaceIndicator] Your furnace protect time is expired.");
			}
			unset($this->remain[$location]);
		}
	}

	public function onTouch(PlayerInteractEvent $event){
		$block = $event->getBlock();
		if($block instanceof FurnaceBlock){
			if(isset($this->remain[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()])){
				$data = $this->remain[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()];
				$player = $event->getPlayer();
				if($data[0] !== $player->getName()){
					if(!$player->hasPermission("furnaceindicator.touch")){
						$event->setCancelled();
						$player->sendMessage("[FurnaceIndicator] This furnace is protected by ".$data[0]);
					}
				}
			}
		}
	}
	
	public function onBreak(BlockBreakEvent $event){
		$block = $event->getBlock();
		
		if($block instanceof FurnaceBlock){
			if(isset($this->remain[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()])){
				$data = $this->remain[$block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName()];
				$player = $event->getPlayer();
				if($data[0] !== $player->getName()){
					if(!$player->hasPermission("furnaceindicator.break")){
						$event->setCancelled();
						$player->sendMessage("[FurnaceIndicator] This furnace is protected by ".$data[0]);
					}else{
						$this->getServer()->getScheduler()->cancelTask($data[2]);
						$this->finishSchedule($block->getX().":".$block->getY().":".$block->getZ().":".$block->getLevel()->getName());
						$player->sendMessage("[FurnaceIndicator] This furnace has been unprotected.");
					}
				}
			}
		}
	}
}