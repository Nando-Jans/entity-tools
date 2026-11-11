<?php

namespace NandoJans\EntityTools\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NandoJans\EntityTools\Config\EntityConfig;
use NandoJans\EntityTools\Service\EntityConfigRegistry;
use NandoJans\EntityTools\Service\IndexPageService;
use NandoJans\EntityTools\Service\IndexTable;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractEntityController extends AbstractController
{
    protected IndexPageService $indexPageService;
    protected EntityManagerInterface $entityManager;
    private EntityConfigRegistry $configs;
    private LoggerInterface $logger;

    #[Required]
    public function initializeEntityTools(IndexPageService $indexPageService, EntityManagerInterface $entityManager, EntityConfigRegistry $configs, LoggerInterface $logger): void
    {
        $this->indexPageService = $indexPageService;
        $this->entityManager = $entityManager;
        $this->configs = $configs;
        $this->logger = $logger;
    }

    abstract protected function getEntityClass(): string;

    abstract protected function getFormClass(bool $isNew): string;

    #[Route('/index', name: 'index', options: ['nav' => true, 'entity_config' => true], methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            $data = $this->createIndexTable($request)->toArray();
        } catch (\Throwable $exception) {
            $this->logger->error('Entity index could not be loaded.', ['entity' => $this->getEntityClass(), 'exception' => $exception]);
            $this->addFlash('error', $this->getIndexErrorMessage($exception));
            $config = $this->config();
            $data = [
                'entity' => EntityConfig::getEntityName($this->getEntityClass()),
                'label' => $config->getLabel(),
                'labelPlural' => $config->getLabelPlural(),
                'items' => [],
                'columns' => $config->getIndexTableColumns(),
                'total' => 0,
                'from' => 0,
                'to' => 0,
                'page' => 1,
                'pages' => 1,
                'sortField' => null,
                'sortDirection' => 'ASC',
                'buttons' => [],
                'showTableCheckboxes' => false,
            ];
        }

        return $this->render($this->getIndexTemplate(), $data);
    }

    #[Route('/add', name: 'add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $class = $this->getEntityClass();
        $entity = new $class();
        $this->configureNewEntity($entity, $request);
        $form = $this->createForm($this->getFormClass(true), $entity, $this->getFormOptions($entity, true));
        return $this->handleForm($request, $form, $entity, true);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $entity = $this->findEntity($id);
        if ($entity === null) {
            $this->addFlash('error', $this->getNotFoundMessage($id));

            return $this->redirectToRoute($this->getIndexRoute());
        }

        $form = $this->createForm($this->getFormClass(false), $entity, $this->getFormOptions($entity, false));
        return $this->handleForm($request, $form, $entity, false);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $entity = $this->findEntity($id);
        if ($entity === null) {
            $this->addFlash('error', $this->getNotFoundMessage($id));

            return $this->redirectToRoute($this->getIndexRoute());
        }

        if (!$this->canDelete($entity)) {
            $this->addFlash('error', $this->getDeleteAccessDeniedMessage());

            return $this->redirectToRoute($this->getIndexRoute());
        }

        if (!$this->isCsrfTokenValid('delete' . $id, $request->request->getString('_token'))) {
            $this->addFlash('error', $this->getInvalidCsrfTokenMessage());
        } else {
            try {
                $this->beforeDelete($entity);
                $this->entityManager->remove($entity);
                $this->entityManager->flush();
                $this->addFlash('success', $this->getDeleteSuccessMessage($entity));
            } catch (\Throwable $exception) {
                $this->logger->error('Entity deletion failed.', ['entity' => $entity::class, 'id' => $id, 'exception' => $exception]);
                $this->addFlash('error', $this->getDeleteErrorMessage($entity, $exception));
            }
        }

        return $this->redirectToRoute($this->getIndexRoute());
    }

    protected function createIndexTable(Request $request): IndexTable
    {
        return $this->indexPageService->getIndexTable($request, $this->getEntityClass(), $this->configureIndexQuery(...));
    }

    protected function configureIndexQuery(object $queryBuilder): object
    {
        return $queryBuilder;
    }

    protected function configureNewEntity(object $entity, Request $request): void
    {
    }

    protected function beforeSave(object $entity, bool $isNew): void
    {
    }

    protected function beforeValidation(object $entity, bool $isNew, FormInterface $form): void
    {
    }

    protected function beforeDelete(object $entity): void
    {
    }

    protected function canDelete(object $entity): bool
    {
        return true;
    }

    protected function getFormOptions(object $entity, bool $isNew): array
    {
        return [];
    }

    protected function getIndexTemplate(): string
    {
        return 'entity/index.html.twig';
    }

    protected function getEditTemplate(object $entity, bool $isNew): string
    {
        return 'entity/edit.html.twig';
    }

    protected function getTemplateData(object $entity, bool $isNew): array
    {
        return ['label' => $this->config()->getLabel(), 'editOrAdd' => $isNew ? 'Toevoegen' : 'Aanpassen'];
    }

    protected function getIndexRoute(): string
    {
        return 'app_' . EntityConfig::getEntityName($this->getEntityClass()) . '_index';
    }

    protected function getSaveRedirect(FormInterface $form, object $entity, bool $isNew): Response
    {
        return $this->redirectToRoute($this->getIndexRoute());
    }

    protected function getSaveSuccessMessage(object $entity, bool $isNew): string
    {
        return $this->config()->getLabel() . ($isNew ? ' toegevoegd.' : ' aangepast.');
    }

    protected function getSaveErrorMessage(object $entity, bool $isNew, \Throwable $exception): string
    {
        return $this->config()->getLabel() . ' kon niet worden opgeslagen. Probeer het opnieuw.';
    }

    protected function getIndexErrorMessage(\Throwable $exception): string
    {
        return $this->config()->getLabelPlural() . ' konden niet worden geladen. Probeer het opnieuw.';
    }

    protected function getDeleteSuccessMessage(object $entity): string
    {
        return $this->config()->getLabel() . ' verwijderd.';
    }

    protected function getDeleteErrorMessage(object $entity, \Throwable $exception): string
    {
        return $this->config()->getLabel() . ' kon niet worden verwijderd. Probeer het opnieuw.';
    }

    protected function getDeleteAccessDeniedMessage(): string
    {
        return 'Je hebt geen rechten om deze actie uit te voeren.';
    }

    protected function getInvalidCsrfTokenMessage(): string
    {
        return 'De actie kon niet worden uitgevoerd. Probeer het opnieuw.';
    }

    protected function getNotFoundMessage(int $id): string
    {
        return sprintf('%s met id %d kon niet worden gevonden.', $this->config()->getLabel(), $id);
    }

    private function config(): \NandoJans\EntityTools\Config\EntityConfig
    {
        return $this->configs->get($this->getEntityClass());
    }

    private function findEntity(int $id): ?object
    {
        return $this->entityManager->getRepository($this->getEntityClass())->find($id);
    }

    private function handleForm(Request $request, FormInterface $form, object $entity, bool $isNew): Response
    {
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->beforeValidation($entity, $isNew, $form);

            if ($form->isValid()) {
                try {
                    $this->beforeSave($entity, $isNew);
                    $this->entityManager->persist($entity);
                    $this->entityManager->flush();
                    $this->addFlash('success', $this->getSaveSuccessMessage($entity, $isNew));

                    return $this->getSaveRedirect($form, $entity, $isNew);
                } catch (\Throwable $exception) {
                    $this->logger->error('Entity save failed.', ['entity' => $entity::class, 'is_new' => $isNew, 'exception' => $exception]);
                    throw $exception;
                    $this->addFlash('error', $this->getSaveErrorMessage($entity, $isNew, $exception));
                }
            } else {
                $this->addFormErrorFlashes($form);
            }
        }

        return $this->render($this->getEditTemplate($entity, $isNew), [...$this->getTemplateData($entity, $isNew), 'form' => $form->createView()]);
    }

    private function addFormErrorFlashes(FormInterface $form): void
    {
        $hasErrors = false;
        foreach ($form->getErrors(true, true) as $error) {
            $hasErrors = true;
            $origin = $error->getOrigin();
            $label = $origin?->getConfig()->getOption('label');
            if (!is_string($label) || $label === '') {
                $label = $origin?->getPropertyPath() ?: $origin?->getName();
            }

            $this->addFlash('error', $label ? sprintf('%s: %s', $label, $error->getMessage()) : $error->getMessage());
        }

        if (!$hasErrors) {
            $this->addFlash('error', 'Het formulier bevat ongeldige gegevens.');
        }
    }

    protected function hasFlow(): bool
    {
        return false;
    }

    protected function getFlowFormClass(): ?string
    {
        return null;
    }

    #[Route('/flow', name: 'flow', methods: ['GET', 'POST'])]
    public function flow(Request $request): Response
    {
        $flowFormClass = $this->getFlowFormClass();

        if ($flowFormClass === null) {
            throw $this->createNotFoundException();
        }

        $entityClass = $this->getEntityClass();
        $entity = new $entityClass();

        $this->configureNewEntity($entity, $request);

        $flow = $this->createForm(
            $flowFormClass,
            $entity,
            $this->getFormOptions($entity, true),
        );

        $flow->handleRequest($request);

        if ($flow->isSubmitted()) {
            $entity = $flow->getData();
            $this->beforeValidation($entity, true);

            if ($flow->isValid() && $flow->isFinished()) {
                try {
                    $this->beforeSave($entity, true);

                    $this->entityManager->persist($entity);
                    $this->entityManager->flush();

                    $this->addFlash(
                        'success',
                        $this->getSaveSuccessMessage($entity, true),
                    );

                    return $this->getSaveRedirect($flow, $entity, true);
                } catch (\Throwable $exception) {
                    $this->logger->error('Entity flow save failed.', [
                        'entity' => $entity::class,
                        'exception' => $exception,
                    ]);

                    $this->addFlash(
                        'error',
                        $this->getSaveErrorMessage($entity, true, $exception),
                    );
                }
            } elseif (!$flow->isValid()) {
                $this->addFormErrorFlashes($flow);
            }
        }

        $stepForm = $flow->getStepForm();

        return $this->render(
            $this->getFlowTemplate($entity),
            [
                ...$this->getTemplateData($entity, true),
                'form' => $stepForm->createView(),
            ],
        );
    }

    protected function getFlowTemplate(object $entity): string
    {
        return '';
    }
}
