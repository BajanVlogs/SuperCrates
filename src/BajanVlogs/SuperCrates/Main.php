<?php

namespace BajanVlogs\SuperCrates;

use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\UUID;
use pocketmine\math\Vector3;
use pocketmine\player\Player; // Ensure this is the correct namespace for Player

class Main extends PluginBase implements Listener {
    public $Crate = [];
    public $locations = [
        "Voting" => "-69:118:-54",
        "Elite" => "-69:118:-44",
        "Legendary" => "-69:118:-49",
    ];

    /** @var Config */
    public $items;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->saveResource("items.yml");
        $this->items = new Config($this->getDataFolder() . "items.yml", Config::YAML);
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        foreach($this->locations as $crate => $xyz){
            $xyz = explode(":", $xyz);
            $cpos = new Vector3((int)$xyz[0], (int)$xyz[1], (int)$xyz[2]);
            $this->spawnCrate($crate, $cpos, $player);
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $pos = new Vector3($block->getX(), $block->getY(), $block->getZ());
        $item = $event->getItem();
        foreach($this->locations as $crate => $xyz){
            $xyz = explode(":", $xyz);
            $cpos = new Vector3((int)$xyz[0], (int)$xyz[1], (int)$xyz[2]);
            if($pos->equals($cpos)){
                $this->handleCrateInteraction($player, $crate, $block, $item);
                $event->setCancelled();
                return;
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $block = $event->getBlock();
        $this->getChestReady($block);
    }

    public function spawnCrate(string $crate, Vector3 $position, Player $player): void {
        // Your spawnCrate method logic here
    }

    public function handleCrateInteraction(Player $player, string $crate, Block $block, Item $item): void {
        // Your handleCrateInteraction method logic here
    }

    public function getChestReady(Block $block): void {
        // Your getChestReady method logic here
    }

    // Other methods...
}
