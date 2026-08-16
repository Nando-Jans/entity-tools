<?php
namespace NandoJans\EntityTools\Service;
use Closure;
use NandoJans\EntityTools\Config\EntityConfig;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
final class IndexTable
{
    private ?Closure $routeHandler = null;
    public function __construct(private string $entity, private array $entities, private array $columns, private int $total, private int $from, private int $to, private int $page, private int $pages, private ?string $sortField, private string $sortDirection, private EntityConfig $config, private UrlGeneratorInterface $router, private UserInterface $user) {}
    public function setRouteHandler(Closure $handler): self { $this->routeHandler=$handler; return $this; }
    public function toArray(): array
    {
        return ['entity'=>$this->entity,'label'=>$this->config->getLabel(),'labelPlural'=>$this->config->getLabelPlural(),'items'=>$this->rows(),'columns'=>$this->columns,'total'=>$this->total,'from'=>$this->from,'to'=>$this->to,'page'=>$this->page,'pages'=>$this->pages,'sortField'=>$this->sortField,'sortDirection'=>$this->sortDirection,'buttons'=>$this->config->getIndexTableButtons($this->user),'showTableCheckboxes'=>$this->config->showTableCheckboxes($this->user)];
    }
    private function rows(): array
    {
        $rows=[];
        foreach ($this->entities as $entity) {
            $row=[];
            foreach ($this->columns as $key=>$column) {
                $getter=$column['getter']??null;
                $row[$key]=is_string($getter)&&method_exists($entity,$getter)?$entity->$getter():(is_callable($getter)?$getter($entity):'');
            }
            $row['_route']=$this->route($entity); $row['_id']=$entity->getId(); $row['_label']=$this->label($entity); $rows[]=$row;
        }
        return $rows;
    }
    private function route(object $entity): string
    {
        if ($this->routeHandler) return ($this->routeHandler)($this->router,$entity);
        try { return $this->router->generate('app_'.EntityConfig::getEntityName($entity::class).'_edit',['id'=>$entity->getId()]); }
        catch (RouteNotFoundException) { return ''; }
    }
    private function label(object $entity): string
    {
        foreach (['__toString','getTitle','getName'] as $method) if (method_exists($entity,$method)) return (string)$entity->$method();
        return 'Item '.$entity->getId();
    }
}
