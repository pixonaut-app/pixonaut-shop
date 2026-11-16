<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Archive;

use Brain\Monkey\Filters;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Archive\AttributeDependencies;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(AttributeDependencies::class)]
final class AttributeDependenciesTest extends TestCase
{
    private const FRAMING = 'pa_framing';

    private const FRAME_COLOUR = 'pa_frame-colour';

    private const SIZE = 'pa_size';

    public function testWithoutThePluginNothingDepends(): void
    {
        $dependencies = new AttributeDependencies();

        self::assertFalse($dependencies->is_dependent(self::FRAME_COLOUR));
        self::assertSame([], $dependencies->dependents_under(self::FRAMING));
        self::assertSame([], $dependencies->nested([self::FRAMING, self::FRAME_COLOUR]));
    }

    public function testListsADependentUnderEachValueItAppliesUnder(): void
    {
        $this->answer([self::FRAME_COLOUR => [self::FRAMING => ['frame', 'hanger']]]);
        $dependencies = new AttributeDependencies();

        self::assertTrue($dependencies->is_dependent(self::FRAME_COLOUR));
        self::assertFalse($dependencies->is_dependent(self::FRAMING));
        self::assertSame(['frame' => [self::FRAME_COLOUR], 'hanger' => [self::FRAME_COLOUR]], $dependencies->dependents_under(self::FRAMING));
        self::assertSame([self::FRAME_COLOUR], $dependencies->nested([self::FRAMING, self::FRAME_COLOUR, self::SIZE]));
    }

    public function testKeepsOnlyTheDocumentedShape(): void
    {
        $this->answer([
            self::FRAME_COLOUR => [self::FRAMING => ['frame', '', 3]],
            self::SIZE => 'large',
            0 => [self::FRAMING => ['frame']],
            'pa_finish' => [self::FRAMING => []],
        ]);
        $dependencies = new AttributeDependencies();

        self::assertSame(['frame' => [self::FRAME_COLOUR]], $dependencies->dependents_under(self::FRAMING));
        self::assertFalse($dependencies->is_dependent(self::SIZE));
        self::assertFalse($dependencies->is_dependent('pa_finish'));
    }

    public function testAnAnswerThatIsNoListMeansNothingDepends(): void
    {
        $this->answer('pa_frame-colour');

        self::assertSame([], (new AttributeDependencies())->dependents_under(self::FRAMING));
    }

    public function testADependentWhoseParentIsMissingKeepsItsOwnGroupAndItsDependentsNestUnderIt(): void
    {
        $this->answer([
            self::FRAME_COLOUR => ['pa_gone' => ['frame']],
            'pa_frame-finish' => [self::FRAME_COLOUR => ['oak']],
        ]);

        self::assertSame(['pa_frame-finish'], (new AttributeDependencies())->nested([self::FRAME_COLOUR, 'pa_frame-finish']));
    }

    public function testTheFirstOfACircleKeepsItsOwnGroupAndTheRestNestUnderIt(): void
    {
        $this->answer([
            'pa_a' => ['pa_b' => ['x']],
            'pa_b' => ['pa_a' => ['y']],
        ]);

        self::assertSame(['pa_b'], (new AttributeDependencies())->nested(['pa_a', 'pa_b']));
    }

    public function testADependentUnderANestedOneNestsToo(): void
    {
        $this->answer([
            self::FRAME_COLOUR => [self::FRAMING => ['frame']],
            'pa_frame-finish' => [self::FRAME_COLOUR => ['oak']],
        ]);

        self::assertSame(
            [self::FRAME_COLOUR, 'pa_frame-finish'],
            (new AttributeDependencies())->nested([self::FRAMING, self::FRAME_COLOUR, 'pa_frame-finish'])
        );
    }

    public function testAnAttributeDeeperThanTheLimitKeepsItsOwnGroup(): void
    {
        $chain = [self::FRAMING, 'pa_level-1', 'pa_level-2', 'pa_level-3'];
        $answer = [];

        foreach (\array_slice($chain, 1) as $level => $taxonomy) {
            $answer[$taxonomy] = [$chain[$level] => ['x']];
        }

        $this->answer($answer);

        self::assertSame(
            \array_slice($chain, 1, AttributeDependencies::MAX_DEPTH),
            (new AttributeDependencies())->nested($chain)
        );
    }

    private function answer(mixed $answer): void
    {
        Filters\expectApplied(AttributeDependencies::FILTER)->andReturn($answer);
    }
}
