<?php

namespace BajanVlogs\SuperCrates;

use pocketmine\item\Item;
use pocketmine\utils\TextFormat as TF;
use pocketmine\scheduler\Task;
use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\world\sound\BlazeShootSound;

class Task extends Task {
    public $plugin;
    public $block;
    public $item;
    public $player;

    public function __construct(Main $plugin, Block $block, Item $item, Player $player) {
        $this->plugin = $plugin;
        $this->block = $block;
        $this->item = $item;
        $this->player = $player;
    }

    public function onRun(): void {
        if (!isset($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()])) {
            $this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] = 0;
        }
        $this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()]++;
        $api = $this->plugin;

        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] > 45 && 
            $this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] < 47) {
            return;
        }

        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] == 47) {
            $this->player->sendMessage(TF::GREEN . "You have won " . TF::GOLD . $this->item->getName() . TF::GREEN . " from the crate!");
            $this->player->getInventory()->addItem($this->item);
            return;
        }

        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] == 48) {
            $api->despawnItem($this->block);
            $api->closeChest($this->block);
            $api->getChestReady($this->block);
            unset($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()]);
            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] == 45) {
            $api->despawnItem($this->block);
            $api->closeChest($this->block);
            $item = Item::get($this->item->getId(), 0, 1);
            $this->block->getPosition()->getWorld()->addSound($this->block->getPosition(), new BlazeShootSound());
            $item->setCustomName(TF::GREEN . ">> " . TF::GOLD . $item->getName() . TF::GREEN . " <<");
            $api->spawnItem($this->block, $item);
            return;
        }

        $api->despawnItem($this->block);

        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] == 25) {
            $this->resetChest(8);
        }
        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] == 35) {
            $this->resetChest(10);
        }
        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] == 38) {
            $this->resetChest(13);
        }
        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] == 40) {
            $this->resetChest(16);
        }
        if ($this->plugin->chest[$this->block->getPosition()->getX() . $this->block->getPosition()->getZ()] == 43) {
            $this->resetChest(20);
        }

        $api->spawnItem($this->block);
    }

    public function resetChest(int $time): void {
        $this->plugin->getScheduler()->cancelTask($this->getTaskId());
        $task = new Task($this->plugin, $this->block, $this->item, $this->player);
        $this->plugin->getScheduler()->scheduleRepeatingTask($task, $time);
    }
}
