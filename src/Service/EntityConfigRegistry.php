<?php
namespace NandoJans\EntityTools\Service;
use NandoJans\EntityTools\Config\EntityConfig;
final class EntityConfigRegistry
{
    private array $configs = [];
    public function __construct(iterable $configs)
    {
        foreach ($configs as $config) $this->configs[$config->getEntityClass()] = $config;
    }
    public function get(string $entityClass): EntityConfig
    {
        return $this->configs[$entityClass] ?? throw new \LogicException("No entity config is registered for $entityClass.");
    }
    public function findByEntityName(string $entityName): ?EntityConfig
    {
        foreach ($this->configs as $config) if (EntityConfig::getEntityName($config->getEntityClass())===$entityName) return $config;
        return null;
    }
}
