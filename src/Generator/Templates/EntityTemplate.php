<?= $phpOpener ?? '' ?>

namespace <?= $entityNamespace ?? '' ?>;

<?php if (!empty($useAttributes)) : ?>
use Composite\DB\Entity\Attributes\{<?= implode(', ', $useAttributes) ?>};
<?php endif; ?>
<?php foreach($useNamespaces ?? [] as $namespace) : ?>
use <?=$namespace?>;
<?php endforeach; ?>

#[Table(db: '<?= $dbName ?? '' ?>', name: '<?= $tableName ?? '' ?>')]
<?php foreach($indexes ?? [] as $index) : ?>
<?=$index?>

<?php endforeach; ?>
class <?=$entityClassShortname??''?> extends AbstractEntity
{
<?php foreach($traits ?? [] as $trait) : ?>
    use <?= $trait ?>;

<?php endforeach; ?>
<?php foreach($properties ?? [] as $property) : ?>
<?php foreach($property['attributes'] as $attribute) : ?>
    <?= $attribute ?>

<?php endforeach; ?>
    <?= $property['var'] ?>;

<?php endforeach; ?>
    public function __construct(
<?php foreach($constructorParams ?? [] as $param) : ?>
<?php foreach($param['attributes'] as $attribute) : ?>
        <?= $attribute ?>

<?php endforeach; ?>
        <?= $param['var'] ?>,
<?php endforeach; ?>
    ) {}
}
