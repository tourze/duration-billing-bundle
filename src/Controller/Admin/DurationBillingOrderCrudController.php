<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

#[AdminCrud(
    routePath: '/duration-billing/order',
    routeName: 'duration_billing_order'
)]
final class DurationBillingOrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DurationBillingOrder::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('计费订单')
            ->setEntityLabelInPlural('时长计费订单管理')
            ->setPageTitle(Crud::PAGE_INDEX, '计费订单列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建计费订单')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑计费订单')
            ->setPageTitle(Crud::PAGE_DETAIL, '计费订单详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['orderCode', 'userId'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield AssociationField::new('product', '计费产品')
            ->setRequired(true)
            ->autocomplete()
        ;
        yield TextField::new('userId', '用户ID')
            ->setMaxLength(255)
            ->setRequired(true)
        ;
        yield TextField::new('orderCode', '订单编号')
            ->setMaxLength(255)
            ->setRequired(true)
        ;
        yield ChoiceField::new('status', '订单状态')
            ->setChoices(array_combine(
                array_map(fn (OrderStatus $s) => $s->getLabel(), OrderStatus::cases()),
                OrderStatus::cases()
            ))
            ->renderExpanded(false)
        ;
        yield DateTimeField::new('startTime', '开始时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setRequired(true)
        ;
        yield DateTimeField::new('endTime', '结束时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnIndex()
        ;
        yield DateTimeField::new('paymentTime', '支付时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnIndex()
        ;
        yield DateTimeField::new('frozenAt', '冻结时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnIndex()
        ;
        yield MoneyField::new('prepaidAmount', '预付金额')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->setRequired(true)
        ;
        yield MoneyField::new('actualAmount', '实际金额')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->hideOnIndex()
        ;
        yield IntegerField::new('frozenMinutes', '冻结时间(分钟)')
            ->setHelp('订单级别的冻结时长设置')
            ->hideOnIndex()
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
            ->add(EntityFilter::new('product', '计费产品'))
            ->add(TextFilter::new('userId', '用户ID'))
            ->add(TextFilter::new('orderCode', '订单编号'))
            ->add(ChoiceFilter::new('status', '订单状态')->setChoices(
                array_combine(
                    array_map(fn (OrderStatus $s) => $s->getLabel(), OrderStatus::cases()),
                    array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases())
                )
            ))
            ->add(NumericFilter::new('prepaidAmount', '预付金额'))
            ->add(NumericFilter::new('actualAmount', '实际金额'))
            ->add(DateTimeFilter::new('startTime', '开始时间'))
            ->add(DateTimeFilter::new('endTime', '结束时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
