<?php

namespace Jankx\Extensions\MembershipLevels;

abstract class Block
{
    protected $blockId;
    protected $blockPath;

    public function __construct($blockPath = null)
    {
        $this->blockPath = $blockPath;
    }

    public function getBlockId()
    {
        return $this->blockId;
    }

    public function setBlockPath(string $path): void
    {
        $this->blockPath = $path;
    }

    public function boot(): void
    {
        if (!$this->blockPath) {
            $this->blockPath = $this->resolveBlockPath();
        }
        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
    }

    public function register(): void
    {
        $blockJson = $this->blockPath . '/block.json';
        if (file_exists($blockJson)) {
            $metadata = json_decode(file_get_contents($blockJson), true);
            $name = $metadata['name'] ?? '';
            if ($name && \WP_Block_Type_Registry::get_instance()->is_registered($name)) {
                return;
            }
        }

        $args = [];
        if (method_exists($this, 'render')) {
            $args['render_callback'] = [$this, 'render'];
        }
        register_block_type_from_metadata($this->blockPath, $args);
    }

    protected function resolveBlockPath(): string
    {
        $blocksDir = dirname(__DIR__, 2) . '/blocks/' . basename($this->blockId);
        if (is_dir($blocksDir)) {
            return $blocksDir;
        }
        return '';
    }
}