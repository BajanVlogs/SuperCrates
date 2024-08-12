<?php

namespace BajanVlogs\SuperCrates;

use pocketmine\item\Item;
use pocketmine\utils\TextFormat as TF;
use pocketmine\scheduler\Task;
use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\world\sound\BlazeShootSound;

class CrateTask extends Task {
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
        $blockPos = $this->block->getPosition();
        $blockKey = $blockPos->getX() . $blockPos->getZ();

        if (!isset($this->plugin->chest[$blockKey])) {
            $this->plugin->chest[$blockKey] = 0;
        }

        $this->plugin->chest[$blockKey]++;
        $api = $this->plugin;

        if ($this->plugin->chest[$blockKey] > 45 && $this->plugin->chest[$blockKey] < 47) {
            return;
        }

        if ($this->plugin->chest[$blockKey] == 47) {
            $this->player->sendMessage(TF::GREEN . "You have won " . TF::GOLD . $this->item->getName() . TF::GREEN . " from the crate!");
            $this->player->getInventory()->addItem($this->item);
            return;
        }

        if ($this->plugin->chest[$blockKey] == 48) {
            $api->despawnItem($this->block);
            $api->closeChest($this->block);
            $api->getChestReady($this->block);
            unset($this->plugin->chest[$blockKey]);
            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        if ($this->plugin->chest[$blockKey] == 45) {
            $api->despawnItem($this->block);
            $api->closeChest($this->block);
            $item = Item::get($this->item->getId(), 0, 1);
            $blockPos->getWorld()->addSound($blockPos, new BlazeShootSound());
            $item->setCustomName(TF::GREEN . ">> " . TF::GOLD . $item->getName() . TF::GREEN . " <<");
            $api->spawnItem($this->block, $item);
            return;
        }

        $api->despawnItem($this->block);

        if ($this->plugin->chest[$blockKey] == 25) {
            $this->resetChest(8);
        }
        if ($this->plugin->chest[$blockKey] == 35) {
            $this->resetChest(10);
        }
        if ($this->plugin->chest[$blockKey] == 38) {
            $this->resetChest(13);
        }
        if ($this->plugin->chest[$blockKey] == 40) {
            $this->resetChest(16);
        }
        if ($this->plugin->chest[$blockKey] == 43) {
            $this->resetChest(20);
        }

        $api->spawnItem($this->block);
    }

    public function resetChest(int $time): void {
        $this->plugin->getScheduler()->cancelTask($this->getTaskId());
        $task = new CrateTask($this->plugin, $this->block, $this->item, $this->player);
        $this->plugin->getScheduler()->scheduleRepeatingTask($task, $time);
    }
}
