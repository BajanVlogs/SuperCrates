<?php

namespace BajanVlogs\SuperCrates;

use pocketmine\utils\TextFormat as TF;
use pocketmine\scheduler\PluginTask;
use pocketmine\block\Block;
use pocketmine\Player;
use pocketmine\level\sound\BlazeShootSound;

class Task extends PluginTask {
    /** @var Main */
    private $plugin;
    /** @var Block */
    private $block;
    /** @var Item */
    private $item;
    /** @var Player */
    private $player;

    public function __construct(Main $plugin, Block $block, Item $item, Player $player) {
        parent::__construct($plugin);
        $this->plugin = $plugin;
        $this->block = $block;
        $this->item = $item;
        $this->player = $player;
    }

    public function onRun(int $currentTick) {
        if (!isset($this->plugin->chest[$this->block->x . $this->block->z])) {
            $this->plugin->chest[$this->block->x . $this->block->z] = 0;
        }
        
        $this->plugin->chest[$this->block->x . $this->block->z]++;

        $api = $this->plugin;
        $chestId = $this->block->x . $this->block->z;

        if ($this->plugin->chest[$chestId] > 45 && $this->plugin->chest[$chestId] < 47) {
            return;
        }

        switch ($this->plugin->chest[$chestId]) {
            case 47:
                $this->player->sendMessage(TF::GREEN . "You have won " . TF::GOLD . $this->item->getName() . TF::GREEN . " from the crate!");
                $this->player->getInventory()->addItem($this->item);
                break;

            case 48:
                $api->despawnItem($this->block);
                $api->closeChest($this->block);
                $api->getChestReady($this->block);
                unset($this->plugin->chest[$chestId]);
                $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
                break;

            case 45:
                $api->despawnItem($this->block);
                $api->closeChest($this->block);
                $prizeItem = Item::get($this->item->getId(), 0, 1);
                $this->block->getLevel()->addSound(new BlazeShootSound($this->block));
                $prizeItem->setCustomName(TF::GREEN . ">> " . TF::GOLD . $prizeItem->getName() . TF::GREEN . " <<");
                $api->spawnItem($this->block, $prizeItem);
                break;

            default:
                $api->despawnItem($this->block);

                if ($this->plugin->chest[$chestId] == 25) $this->resetChest(8);
                if ($this->plugin->chest[$chestId] == 35) $this->resetChest(10);
                if ($this->plugin->chest[$chestId] == 38) $this->resetChest(13);
                if ($this->plugin->chest[$chestId] == 40) $this->resetChest(16);
                if ($this->plugin->chest[$chestId] == 43) $this->resetChest(20);

                $api->spawnItem($this->block);
                break;
        }

        return true;
    }

    private function resetChest(int $time) {
        $this->plugin->getServer()->getScheduler()->cancelTask($this->getTaskId());
        $task = new Task($this->plugin, $this->block, $this->item, $this->player);
        $this->plugin->getServer()->getScheduler()->scheduleRepeatingTask($task, $time);
        return true;
    }
}
