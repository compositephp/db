<?php declare(strict_types=1);

namespace Composite\DB\Tests\Entity\TestStand;

use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Attributes;

#[Attributes\Table(db: 'sqlite', name: 'Diversity')]
class TestDiversityEntity extends AbstractEntity
{
    #[Attributes\PrimaryKey(autoIncrement: true)]
    public readonly int $id;

    public string $str1;
    public ?string $str2;
    public string $str3 = 'str3 def';
    public ?string $str4 = '';
    public ?string $str5 = null;
    
    public int $int1;
    public ?int $int2;
    public int $int3 = 33;
    public ?int $int4 = 44;
    public ?int $int5 = null;

    public float $float1;
    public ?float $float2;
    public float $float3 = 3.9;
    public ?float $float4 = 4.9;
    public ?float $float5 = null;

    public bool $bool1;
    public ?bool $bool2;
    public bool $bool3 = true;
    public ?bool $bool4 = false;
    public ?bool $bool5 = null;

    public array $arr1;
    public ?array $arr2;
    public array $arr3 = [11, 22, 33];
    public ?array $arr4 = [];
    public ?array $arr5 = null;

    public TestBackedStringEnum $benum_str1;
    public ?TestBackedStringEnum $benum_str2;
    public TestBackedStringEnum $benum_str3 = TestBackedStringEnum::Foo;
    public ?TestBackedStringEnum $benum_str4 = TestBackedStringEnum::Bar;
    public ?TestBackedStringEnum $benum_str5 = null;

    public TestBackedIntEnum $benum_int1;
    public ?TestBackedIntEnum $benum_int2;
    public TestBackedIntEnum $benum_int3 = TestBackedIntEnum::FooInt;
    public ?TestBackedIntEnum $benum_int4 = TestBackedIntEnum::BarInt;
    public ?TestBackedIntEnum $benum_int5 = null;

    public TestUnitEnum $uenum1;
    public ?TestUnitEnum $uenum2;
    public TestUnitEnum $uenum3 = TestUnitEnum::Foo;
    public ?TestUnitEnum $uenum4 = TestUnitEnum::Bar;
    public ?TestUnitEnum $uenum5 = null;
    
    public function __construct(
        public \stdClass $obj1,
        public ?\stdClass $obj2,

        public \DateTime $dt1,
        public ?\DateTime $dt2,

        public \DateTimeImmutable $dti1,
        public ?\DateTimeImmutable $dti2,

        public TestSubEntity $entity1,
        public ?TestSubEntity $entity2,

        public TestCastableIntObject $castable_int1,
        public ?TestCastableIntObject $castable_int2,

        public TestCastableStringObject $castable_str1,
        public ?TestCastableStringObject $castable_str2,

        public \stdClass $obj3 = new \stdClass(),
        public ?\stdClass $obj4 = new \stdClass(),
        public ?\stdClass $obj5 = null,

        public \DateTime $dt3 = new \DateTime('2000-01-01 00:00:00'),
        public ?\DateTime $dt4 = new \DateTime(),
        public ?\DateTime $dt5 = null,

        public \DateTimeImmutable $dti3 = new \DateTimeImmutable('2000-01-01 00:00:00'),
        public ?\DateTimeImmutable $dti4 = new \DateTimeImmutable(),
        public ?\DateTimeImmutable $dti5 = null,

        public TestSubEntity $entity3 = new TestSubEntity(),
        public ?TestSubEntity $entity4 = new TestSubEntity(number: 456),
        public ?TestSubEntity $entity5 = null,

        public TestCastableIntObject $castable_int3 = new TestCastableIntObject(946684801), //2000-01-01 00:00:01,
        public ?TestCastableIntObject $castable_int4 = new TestCastableIntObject(946684802), //2000-01-01 00:00:02,
        public ?TestCastableIntObject $castable_int5 = null,

        public TestCastableStringObject $castable_str3 = new TestCastableStringObject('Hello'),
        public ?TestCastableStringObject $castable_str4 = new TestCastableStringObject('World'),
        public ?TestCastableStringObject $castable_str5 = new TestCastableStringObject(null),
    ) {}
}