<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'merchant-product-create', title: 'Product Create', description: 'The right tool for adding a brand-new product, article, or item to the catalog. Give it plain values (name, product number, price, tax rate) — it resolves tax rate to tax ID, currency ISO code to currency ID, exact category names to category IDs, and builds the nested price structure. New products only; shopware-entity-upsert is the raw fallback for editing existing records. Defaults to dryRun=true. Returns the generated payload in dryRun mode, or the created product ID on commit.')]
#[McpToolGroup('merchant-catalog')]
#[McpToolRequires('product:create')]
#[McpToolRequires('product:read')]
#[McpToolRequires('tax:read')]
#[McpToolRequires('currency:read')]
class ProductCreateTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(
        string $name,
        string $productNumber,
        float $grossPrice,
        float $taxRate = 19,
        string $currencyCode = 'EUR',
        int $stock = 0,
        string $description = '',
        string $categories = '',
        bool $active = true,
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'product:create', 'product:read', 'tax:read', 'currency:read')) {
            return $error;
        }

        $taxId = $this->resolveTaxId($taxRate, $context);

        if ($taxId === null) {
            return $this->error(\sprintf('No tax found with rate %.2f%%. Create the tax first or use a different rate.', $taxRate));
        }

        $currencyId = $this->resolveCurrencyId($currencyCode, $context);

        if ($currencyId === null) {
            return $this->error(\sprintf('No currency found with ISO code "%s".', $currencyCode));
        }

        $netPrice = round($grossPrice / (1 + $taxRate / 100), 4);

        $payload = [
            'id' => Uuid::randomHex(),
            'name' => $name,
            'productNumber' => $productNumber,
            'stock' => $stock,
            'active' => $active,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => $currencyId,
                'gross' => $grossPrice,
                'net' => $netPrice,
                'linked' => true,
            ]],
        ];

        if ($description !== '') {
            $payload['description'] = $description;
        }

        if ($categories !== '') {
            $categoryResult = $this->resolveCategoryIds($categories, $context);

            if (\is_string($categoryResult)) {
                return $this->error($categoryResult);
            }

            if ($categoryResult !== []) {
                $payload['categories'] = array_map(fn (string $id) => ['id' => $id], $categoryResult);
            }
        }

        if ($dryRun) {
            $duplicateCriteria = new Criteria();
            $duplicateCriteria->addFilter(new EqualsFilter('productNumber', $productNumber));
            $duplicateCriteria->setLimit(1);
            if ($this->registry->getRepository('product')->searchIds($duplicateCriteria, $context)->firstId() !== null) {
                return $this->error(\sprintf('A product with productNumber "%s" already exists. Choose a different number or use dryRun=false to overwrite via upsert.', $productNumber));
            }

            return $this->success($payload, ['dryRun' => true]);
        }

        $repository = $this->registry->getRepository('product');

        try {
            $repository->upsert([$payload], $context);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(['productId' => $payload['id'], 'productNumber' => $productNumber], ['dryRun' => false]);
    }

    private function resolveTaxId(float $taxRate, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('taxRate', $taxRate));
        $criteria->setLimit(1);

        $result = $this->registry->getRepository('tax')->searchIds($criteria, $context);

        return $result->firstId();
    }

    private function resolveCurrencyId(string $isoCode, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', $isoCode));
        $criteria->setLimit(1);

        $result = $this->registry->getRepository('currency')->searchIds($criteria, $context);

        return $result->firstId();
    }

    /**
     * @return list<string>|string list of resolved IDs, or an error message string if any name could not be resolved
     */
    private function resolveCategoryIds(string $categories, Context $context): array|string
    {
        $names = array_values(array_filter(array_map('trim', explode(',', $categories)), static fn (string $n): bool => $n !== ''));

        if ($names === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));

        $ids = $this->registry->getRepository('category')->searchIds($criteria, $context)->getIds();

        if (\count($ids) < \count($names)) {
            return \sprintf(
                'Could not resolve all category names ("%s"): %d of %d found. Check spelling with shopware-entity-search on the category entity.',
                implode('", "', $names),
                \count($ids),
                \count($names),
            );
        }

        return $ids;
    }
}
