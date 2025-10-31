<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;

#[AdminCrud(
    routePath: '/duration-billing/product',
    routeName: 'duration_billing_product'
)]
final class DurationBillingProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DurationBillingProduct::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('计费产品')
            ->setEntityLabelInPlural('时长计费产品管理')
            ->setPageTitle(Crud::PAGE_INDEX, '计费产品列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建计费产品')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑计费产品')
            ->setPageTitle(Crud::PAGE_DETAIL, '计费产品详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['name', 'description'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield TextField::new('name', '产品名称')
            ->setMaxLength(255)
            ->setRequired(true)
        ;
        yield TextareaField::new('description', '产品描述')
            ->hideOnIndex()
        ;
        yield CodeEditorField::new('pricingRuleData', '定价规则')
            ->setLanguage('javascript')
            ->setNumOfRows(10)
            ->hideOnIndex()
        ;
        yield IntegerField::new('freeMinutes', '免费时长(分钟)')
            ->setHelp('设置免费使用的时长，单位为分钟')
        ;
        yield IntegerField::new('freezeMinutes', '冻结时长(分钟)')
            ->setHelp('设置冻结时长，该时长不计费')
            ->hideOnIndex()
        ;
        yield MoneyField::new('minAmount', '最低消费金额')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->hideOnIndex()
        ;
        yield MoneyField::new('maxAmount', '最高消费金额')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->hideOnIndex()
        ;
        yield BooleanField::new('enabled', '是否启用')
            ->renderAsSwitch(true)
        ;
        yield CodeEditorField::new('metadata', '元数据')
            ->setLanguage('javascript')
            ->setNumOfRows(6)
            ->onlyOnDetail()
        ;
        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
        ;
        yield DateTimeField::new('updateTime', '更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('enabled', '是否启用'))
            ->add(NumericFilter::new('freeMinutes', '免费时长'))
            ->add(NumericFilter::new('minAmount', '最低消费金额'))
            ->add(NumericFilter::new('maxAmount', '最高消费金额'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
