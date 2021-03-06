<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\Sylius\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Behat\Context\Setup\PromotionContext;
use Sylius\Component\Core\Factory\ActionFactoryInterface;
use Sylius\Component\Core\Factory\RuleFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CouponInterface;
use Sylius\Component\Core\Model\PromotionInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Test\Factory\TestPromotionFactoryInterface;
use Sylius\Component\Core\Test\Services\SharedStorageInterface;
use Sylius\Component\Promotion\Factory\CouponFactoryInterface;
use Sylius\Component\Promotion\Model\ActionInterface;
use Sylius\Component\Promotion\Model\RuleInterface;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;

/**
 * @mixin PromotionContext
 *
 * @author Mateusz Zalewski <mateusz.zalewski@lakion.com>
 */
class PromotionContextSpec extends ObjectBehavior
{
    function let(
        SharedStorageInterface $sharedStorage,
        ActionFactoryInterface $actionFactory,
        CouponFactoryInterface $couponFactory,
        RuleFactoryInterface $ruleFactory,
        TestPromotionFactoryInterface $testPromotionFactory,
        PromotionRepositoryInterface $promotionRepository,
        ObjectManager $objectManager
    ) {
        $this->beConstructedWith(
            $sharedStorage,
            $actionFactory,
            $couponFactory,
            $ruleFactory,
            $testPromotionFactory,
            $promotionRepository,
            $objectManager
        );
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Sylius\Behat\Context\Setup\PromotionContext');
    }

    function it_implements_context_interface()
    {
        $this->shouldImplement(Context::class);
    }

    function it_creates_promotion(
        ChannelInterface $channel,
        PromotionInterface $promotion,
        PromotionRepositoryInterface $promotionRepository,
        SharedStorageInterface $sharedStorage,
        TestPromotionFactoryInterface $testPromotionFactory
    ) {
        $sharedStorage->get('channel')->willReturn($channel);

        $testPromotionFactory->createForChannel('Super promotion', $channel)->willReturn($promotion);

        $promotionRepository->add($promotion)->shouldBeCalled();
        $sharedStorage->set('promotion', $promotion)->shouldBeCalled();

        $this->thereIsPromotion('Super promotion');
    }

    function it_creates_promotion_with_coupon(
        SharedStorageInterface $sharedStorage,
        CouponFactoryInterface $couponFactory,
        TestPromotionFactoryInterface $testPromotionFactory,
        PromotionRepositoryInterface $promotionRepository,
        ChannelInterface $channel,
        CouponInterface $coupon,
        PromotionInterface $promotion
    ) {
        $couponFactory->createNew()->willReturn($coupon);
        $coupon->setCode('Coupon galore')->shouldBeCalled();

        $sharedStorage->get('channel')->willReturn($channel);
        $testPromotionFactory->createForChannel('Promotion galore', $channel)->willReturn($promotion);
        $promotion->addCoupon($coupon)->shouldBeCalled();
        $promotion->setCouponBased(true)->shouldBeCalled();

        $promotionRepository->add($promotion)->shouldBeCalled();
        $sharedStorage->set('promotion', $promotion)->shouldBeCalled();
        $sharedStorage->set('coupon', $coupon)->shouldBeCalled();

        $this->thereIsPromotionWithCoupon('Promotion galore', 'Coupon galore');
    }

    function it_creates_fixed_discount_action_for_promotion(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion
    ) {
        $actionFactory->createFixedDiscount(1000)->willReturn($action);
        $promotion->addAction($action)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->itGivesFixedDiscountToEveryOrder($promotion, 1000);
    }

    function it_creates_percentage_discount_action_for_promotion(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion,
        SharedStorageInterface $sharedStorage
    ) {
        $sharedStorage->get('promotion')->willReturn($promotion);

        $actionFactory->createPercentageDiscount(0.1)->willReturn($action);
        $promotion->addAction($action)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->itGivesPercentageDiscountToEveryOrder($promotion, 0.1);
    }

    function it_creates_fixed_discount_promotion_for_cart_with_specified_quantity(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion,
        RuleFactoryInterface $ruleFactory,
        RuleInterface $rule,
        SharedStorageInterface $sharedStorage
    ) {
        $sharedStorage->get('promotion')->willReturn($promotion);

        $actionFactory->createFixedDiscount(1000)->willReturn($action);
        $promotion->addAction($action)->shouldBeCalled();

        $ruleFactory->createCartQuantity(5)->willReturn($rule);
        $promotion->addRule($rule)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->itGivesFixedDiscountToEveryOrderWithQuantityAtLeast($promotion, 1000, '5');
    }

    function it_creates_fixed_discount_promotion_for_cart_with_specified_items_total(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion,
        RuleFactoryInterface $ruleFactory,
        RuleInterface $rule
    ) {
        $actionFactory->createFixedDiscount(1000)->willReturn($action);
        $promotion->addAction($action)->shouldBeCalled();

        $ruleFactory->createItemTotal(5000)->willReturn($rule);
        $promotion->addRule($rule)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->itGivesFixedDiscountToEveryOrderWithItemsTotalAtLeast($promotion, 1000, 5000);
    }

    function it_creates_percentage_shipping_discount_action_for_promotion(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion
    ) {
        $actionFactory->createPercentageShippingDiscount(0.1)->willReturn($action);
        $promotion->addAction($action)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->itGivesPercentageDiscountOnShippingToEveryOrder($promotion, 0.1);
    }

    function it_creates_item_percentage_discount_action_for_promotion_products_with_specific_taxon(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion,
        TaxonInterface $taxon
    ) {
        $taxon->getCode()->willReturn('scottish_kilts');

        $actionFactory->createItemPercentageDiscount(0.1)->willReturn($action);
        $action->getConfiguration()->willReturn(['percentage' => 0.1]);
        $action->setConfiguration([
            'percentage' => 0.1,
            'filters' => [
                'taxons' => ['scottish_kilts'],
            ],
        ])->shouldBeCalled();
        
        $promotion->addAction($action)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->itGivesPercentageOffEveryProductClassifiedAs($promotion, 0.1, $taxon);
    }

    function it_creates_item_fixed_discount_action_for_promotion_products_with_specific_minimum_price(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion
    ) {
        $actionFactory->createItemFixedDiscount(1000)->willReturn($action);
        $action->getConfiguration()->willReturn([]);
        $action->setConfiguration(['filters' => ['price_range' => ['min' => 5000]]])->shouldBeCalled();

        $promotion->addAction($action)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->thisPromotionGivesOffOnEveryProductWithMinimumPriceAt($promotion, 1000, 5000);
    }

    function it_creates_item_fixed_discount_action_for_promotion_products_priced_between(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion
    ) {
        $actionFactory->createItemFixedDiscount(1000)->willReturn($action);
        $action->getConfiguration()->willReturn([]);
        $action->setConfiguration(['filters' => ['price_range' => ['min' => 5000, 'max' => 10000]]])->shouldBeCalled();

        $promotion->addAction($action)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->thisPromotionGivesOffOnEveryProductPricedBetween($promotion, 1000, 5000, 10000);
    }

    function it_creates_item_percentage_discount_action_for_promotion_products_with_specific_minimum_price(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion
    ) {
        $actionFactory->createItemPercentageDiscount(0.1)->willReturn($action);
        $action->getConfiguration()->willReturn([]);
        $action->setConfiguration(['filters' => ['price_range' => ['min' => 5000]]])->shouldBeCalled();

        $promotion->addAction($action)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->thisPromotionPercentageGivesOffOnEveryProductWithMinimumPriceAt($promotion, 0.1, 5000);
    }

    function it_creates_item_percentage_discount_action_for_promotion_products_priced_between(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion
    ) {
        $actionFactory->createItemPercentageDiscount(0.1)->willReturn($action);
        $action->getConfiguration()->willReturn([]);
        $action->setConfiguration(['filters' => ['price_range' => ['min' => 5000, 'max' => 10000]]])->shouldBeCalled();

        $promotion->addAction($action)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->thisPromotionPercentageGivesOffOnEveryProductPricedBetween($promotion, 0.1, 5000, 10000);
    }

    function it_creates_fixed_discount_promotion_with_taxon_rule_for_one_taxon(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion,
        RuleFactoryInterface $ruleFactory,
        RuleInterface $rule,
        TaxonInterface $tanks
    ) {
        $tanks->getCode()->willReturn('tanks');
        $ruleFactory->createTaxon(['tanks'])->willReturn($rule);

        $actionFactory->createFixedDiscount(1000)->willReturn($action);
        $action->getConfiguration()->willReturn([]);
        $action->setConfiguration([])->shouldBeCalled();

        $promotion->addAction($action)->shouldBeCalled();
        $promotion->addRule($rule)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->thePromotionGivesOffIfOrderContainsProductsClassifiedAs($promotion, 1000, $tanks);
    }

    function it_creates_fixed_discount_promotion_with_taxon_rule_for_multiple_taxons(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion,
        RuleFactoryInterface $ruleFactory,
        RuleInterface $rule,
        TaxonInterface $tanks,
        TaxonInterface $cannons
    ) {
        $tanks->getCode()->willReturn('tanks');
        $cannons->getCode()->willReturn('cannons');

        $ruleFactory->createTaxon(['tanks', 'cannons'])->willReturn($rule);

        $actionFactory->createFixedDiscount(1000)->willReturn($action);
        $action->getConfiguration()->willReturn([]);
        $action->setConfiguration([])->shouldBeCalled();

        $promotion->addAction($action)->shouldBeCalled();
        $promotion->addRule($rule)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->thePromotionGivesOffIfOrderContainsProductsClassifiedAsOr($promotion, 1000, [$tanks, $cannons]);
    }

    function it_creates_fixed_discount_promotion_with_total_of_items_from_taxon_rule(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion,
        RuleFactoryInterface $ruleFactory,
        RuleInterface $rule,
        TaxonInterface $tanks
    ) {
        $tanks->getCode()->willReturn('tanks');

        $ruleFactory->createItemsFromTaxonTotal('tanks', 1000)->willReturn($rule);

        $actionFactory->createFixedDiscount(500)->willReturn($action);
        $action->getConfiguration()->willReturn([]);
        $action->setConfiguration([])->shouldBeCalled();

        $promotion->addAction($action)->shouldBeCalled();
        $promotion->addRule($rule)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->thePromotionGivesOffIfOrderContainsProductsClassifiedAsAndPricedAt($promotion, 500, $tanks, 1000);
    }

    function it_creates_a_fixed_discount_promotion_which_contains_a_taxon_rule(
        ActionFactoryInterface $actionFactory,
        ActionInterface $action,
        ObjectManager $objectManager,
        PromotionInterface $promotion,
        RuleFactoryInterface $ruleFactory,
        RuleInterface $rule,
        TaxonInterface $tanks
    ) {
        $tanks->getCode()->willReturn('tanks');

        $ruleFactory->createContainsTaxon('tanks', 10)->willReturn($rule);

        $actionFactory->createFixedDiscount(500)->willReturn($action);
        $action->getConfiguration()->willReturn([]);
        $action->setConfiguration([])->shouldBeCalled();

        $promotion->addAction($action)->shouldBeCalled();
        $promotion->addRule($rule)->shouldBeCalled();

        $objectManager->flush()->shouldBeCalled();

        $this->thePromotionGivesOffIfOrderContainsNumberOfProductsClassifiedAs($promotion, 500, 10, $tanks);
    }
}
