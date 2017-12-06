<?php

namespace BajanVlogs\SuperCrates;

//use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat as TF;
use pocketmine\scheduler\PluginTask;
use pocketmine\block\Block;
use pocketmine\Player;

use pocketmine\level\sound\BlazeShootSound;

class Task extends PluginTask {
	public $plugin, $block, $item, $player;
	public function __construct(Main $plugin, Block $block, Item $item, Player $player){
		parent::__construct($plugin);
		$this->plugin = $plugin;
		$this->block = $block;
		$this->item = $item;
		$this->player = $player;
	}

	public function onRun(int $currenttick){
		if(!isset($this->plugin->chest[$this->block->x . $this->block->z])) $this->plugin->chest[$this->block->x . $this->block->z] = 0;
		$this->plugin->chest[$this->block->x . $this->block->z]++;
		$api = $this->plugin;
		if($this->plugin->chest[$this->block->x . $this->block->z] > 45 && $this->plugin->chest[$this->block->x . $this->block->z] < 47) return;
		if($this->plugin->chest[$this->block->x . $this->block->z] == 47){
			$this->player->sendMessage(TF::GREEN . "You have won " . TF::GOLD . $this->item->getName() . TF::GREEN . " from the crate!");
			$this->player->getInventory()->addItem($this->item);

			return true;
		}
		if($this->plugin->chest[$this->block->x . $this->block->z] == 48){
			$api->despawnItem($this->block);
			$api->closeChest($this->block);
			$api->getChestReady($this->block);
			unset($this->plugin->chest[$this->block->x . $this->block->z]);
			$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());

			return true;
		}
		if($this->plugin->chest[$this->block->x . $this->block->z] == 45){
			$api->despawnItem($this->block);
			$api->closeChest($this->block);
			$item = Item::get($this->item->getId(), 0, 1);
			$this->block->getLevel()->addSound(new BlazeShootSound($this->block));
			$item->setCustomName(TF::GREEN . ">> " . TF::GOLD . $item->getName() . TF::GREEN . " <<");
			$api->spawnItem($this->block, $item);

			return true;
		}
		$api->despawnItem($this->block);
		if($this->plugin->chest[$this->block->x . $this->block->z] == 25) $this->resetChest(8);
		if($this->plugin->chest[$this->block->x . $this->block->z] == 35) $this->resetChest(10);
		if($this->plugin->chest[$this->block->x . $this->block->z] == 38) $this->resetChest(13);
		if($this->plugin->chest[$this->block->x . $this->block->z] == 40) $this->resetChest(16);
		if($this->plugin->chest[$this->block->x . $this->block->z] == 43) $this->resetChest(20);
		$api->spawnItem($this->block);

		return true;
	}

	public function resetChest(int $time){
		$this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
		$task = new Task($this->plugin, $this->block, $this->item, $this->player);
		$this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, $time);

		return true;
	}
}
