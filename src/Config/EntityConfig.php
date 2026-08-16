<?php
namespace NandoJans\EntityTools\Config;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\String\UnicodeString;
#[AutoconfigureTag('nando_jans.entity_tools.entity_config')]
abstract class EntityConfig
{
    public function __construct(private readonly string $entityClass) {}
    abstract public function getIndexTableColumns(): array;
    abstract public function getLabel(): string;
    abstract public function getLabelPlural(): string;
    final public function getEntityClass(): string { return $this->entityClass; }
    public static function getEntityName(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);
        return (new UnicodeString(end($parts)))->snake()->toString();
    }
    public function getIndexTableButtons(UserInterface $user): array
    {
        $entity = self::getEntityName($this->entityClass);
        return ['add' => ['label'=>'Toevoegen','route'=>"app_{$entity}_add",'css'=>'primary','type'=>'link','id'=>"{$entity}-add-button"]];
    }
    public function showTableCheckboxes(UserInterface $user): bool { return false; }
}
