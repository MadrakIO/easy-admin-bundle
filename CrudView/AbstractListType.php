<?php

namespace MadrakIO\Bundle\EasyAdminBundle\CrudView;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormFactory;
use MadrakIO\Bundle\EasyAdminBundle\CrudView\Guesser\FieldTypeGuesser;
use MadrakIO\Bundle\EasyAdminBundle\CrudView\Labeler\FieldTypeLabeler;
use MadrakIO\Bundle\EasyAdminBundle\Security\EasyAdminVoterInterface;

abstract class AbstractListType extends AbstractType
{
    protected $entityClass;
    protected $paginator;
    protected $checkGrants = true;
    protected $grantShowAttribute;
    protected $csvFields;

    public function __construct(EngineInterface $templating, EntityManagerInterface $entityManager, AuthorizationChecker $authorizationChecker, FieldTypeGuesser $fieldTypeGuesser, FormFactory $formFactory, $entityClass)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->authorizationChecker = $authorizationChecker;
        $this->fieldTypeGuesser = $fieldTypeGuesser;
        $this->entityClass = $entityClass;
        $this->formFactory = $formFactory;
    }

    public function setCheckGrants($checkGrants, $grantShowAttribute = EasyAdminVoterInterface::SHOW)
    {
        $this->checkGrants = $checkGrants;
        $this->grantShowAttribute = $grantShowAttribute;
    }

    public function isGranted($attributes, $object = null)
    {
        if ($this->checkGrants === true) {
            return $this->authorizationChecker->isGranted($attributes, $object);
        }

        return true;
    }

    public function isFilterable()
    {
        return $this instanceOf AbstractFilterableListType;
    }

    public function isExportable()
    {
        return count($this->csvFields) > 0;
    }

    public function isSubmitted($request)
    {
        return $this->isFilterable() and $this->createFilterForm($request)->isSubmitted();
    }

    public function setPaginator($paginator)
    {
        $this->paginator = $paginator;
    }

    public function hasPaginator()
    {
        return $this->paginator instanceof \Knp\Component\Pager\Paginator;
    }

    public function createView(Request $request, array $criteria = [])
    {
        $this->build();
        $data = $this->getDataList($request, $criteria);

        return $this->templating->render($this->getListWrapperView(), ['crud_list_data_header' => $this->fields, 'crud_list_data_rows' => $data]);
    }

    public function addToCsv($field)
    {
        $this->csvFields[] = $field;

        return $this;
    }

    public function getCsvFields()
    {
        return $this->csvFields;
    }

    public function createQueryBuilder(Request $request, array $criteria)
    {
        return $this->entityManager->createQueryBuilder()
                                   ->select('entity')
                                   ->from($this->entityClass, 'entity');
    }

    protected function getDataList(Request $request, array $criteria)
    {
        $queryBuilder = $this->createQueryBuilder($request, $criteria);

        if ($this->hasPaginator() === true) {
            $pagination = $this->paginator->paginate($queryBuilder, $request->query->getInt('page', 1), $request->query->getInt('limit', 10));

            $pagination->setItems($this->getData($pagination->getItems()));

            return $pagination;
        }

        return $this->getData($queryBuilder->getQuery()->getResult());
    }

    protected function getData($results)
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($results as &$result) {
            if ($this->isGranted($this->grantShowAttribute, $result) === false) {
                $result = null;

                continue;
            }

            $rowData = [];
            foreach ($this->fields as $field => &$options) {
                $currentFieldData = ($accessor->isReadable($result, $field) === true) ? $accessor->getValue($result, $field) : null;

                if (empty($options['type']) === true) {
                    $options['type'] = $this->fieldTypeGuesser->attemptGuess($field, $currentFieldData);
                }

                if (empty($options['received_default_options']) === true) {
                    $options = $options['type']::getDefaultOptions($options, $field);
                }

                if (isset($options['use_entity']) === true && $options['use_entity'] === true) {
                    $options['entity'] = $result;
                }

                $rowData[] = $options['type']::getDefaultOptions($options + ['data' => $currentFieldData], $field, $result);
            }

            $result = $rowData;
        }

        return $results;
    }

    protected function getListWrapperView()
    {
        if ($this->hasPaginator() === true) {
            return 'MadrakIOEasyAdminBundle:List:Layout/wrapper_pagination.html.twig';
        }

        return 'MadrakIOEasyAdminBundle:List:Layout/wrapper.html.twig';
    }
}
