import { FC } from "react";
import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Pagination,
  Chip,
  Select,
  SelectItem,
  Input,
  Button,
  Skeleton,
} from "@heroui/react";
import {
  LoyaltyPointTransaction,
  LoyaltyTransactionQueryArgs,
} from "@/types/ApiResponse";
import { getFormattedDate } from "@/helpers/getters";
import { useTranslation } from "react-i18next";
import { ArrowUpRight, ArrowDownLeft, RefreshCcw } from "lucide-react";

interface LoyaltyPointsTableProps {
  transactions: LoyaltyPointTransaction[];
  total: number;
  page: number;
  per_page: number;
  query: LoyaltyTransactionQueryArgs;
  isLoading: boolean;
  tableTitle?: string;
  onQueryChange: (args: LoyaltyTransactionQueryArgs) => void;
  onRefresh?: () => void;
}

const statusColor = (status: string) =>
  status === "credited" || status === "completed"
    ? "success"
    : status === "pending"
      ? "warning"
      : "danger";

const LoyaltyPointsTable: FC<LoyaltyPointsTableProps> = ({
  transactions,
  total,
  page,
  per_page,
  query,
  isLoading,
  tableTitle,
  onQueryChange,
  onRefresh,
}) => {
  const { t } = useTranslation();
  const totalPages = Math.ceil(total / per_page);

  const columns = [
    { key: "id", label: t("transaction_id") },
    { key: "order_id", label: t("order_id") },
    { key: "points", label: t("points") },
    { key: "type", label: t("type") },
    { key: "status", label: t("status") },
    { key: "description", label: t("description") },
    { key: "expires_at", label: t("pages.loyaltyPointsPage.expiresAt") },
    { key: "created_at", label: t("date") },
  ];

  const renderCell = (
    transaction: LoyaltyPointTransaction,
    columnKey: string,
  ) => {
    switch (columnKey) {
      case "id":
        return (
          <span className="font-medium text-foreground/80">
            #{transaction.id}
          </span>
        );
      case "order_id":
        return (
          <span className="font-medium text-foreground/80">
            {transaction.order_id ? `#${transaction.order_id}` : "-"}
          </span>
        );
      case "points": {
        const signed =
          transaction.type === "redeemed"
            ? -(transaction.used_points || transaction.points)
            : transaction.points;
        return (
          <div
            className={`flex items-center gap-1 font-bold text-sm ${
              signed >= 0 ? "text-success" : "text-danger"
            }`}
          >
            {signed > 0 ? (
              <ArrowUpRight size={14} />
            ) : (
              <ArrowDownLeft size={14} />
            )}
            <span>{signed > 0 ? `+${signed}` : signed}</span>
          </div>
        );
      }
      case "type":
        return (
          <Chip
            color={transaction.type === "earned" ? "success" : "danger"}
            variant="dot"
            classNames={{ base: "border-none", content: "font-medium" }}
          >
            {t(transaction.type)}
          </Chip>
        );
      case "status":
        return (
          <Chip
            color={statusColor(transaction.status)}
            variant="flat"
            classNames={{ content: "font-medium" }}
          >
            {t(transaction.status)}
          </Chip>
        );
      case "description":
        return (
          <span className="text-foreground/70">
            {transaction.description || "-"}
          </span>
        );
      case "expires_at":
        return (
          <span className="text-foreground/70 whitespace-nowrap">
            {transaction.expires_at
              ? getFormattedDate(transaction.expires_at)
              : "-"}
          </span>
        );
      case "created_at":
        return (
          <span className="text-foreground/70 whitespace-nowrap">
            {getFormattedDate(transaction.created_at)}
          </span>
        );
      default:
        return transaction[columnKey as keyof LoyaltyPointTransaction] || "-";
    }
  };

  const typeOptions = [
    { value: "", label: t("all_types") },
    { value: "earned", label: t("earned") },
    { value: "redeemed", label: t("redeemed") },
  ];

  const topContent = (
    <div className="flex sm:justify-between w-full sm:items-center flex-col sm:flex-row items-start gap-4 mb-2">
      <div className="flex items-center gap-3">
        <h2 className="font-semibold text-lg text-foreground/90 whitespace-nowrap">
          {tableTitle}
        </h2>
        {onRefresh && (
          <Button
            isIconOnly
            size="sm"
            variant="flat"
            color="default"
            onPress={onRefresh}
            isLoading={isLoading}
            aria-label="Refresh"
            className="rounded-full bg-content2/50 text-foreground/70 hover:bg-content2 hover:text-foreground"
          >
            <RefreshCcw size={14} />
          </Button>
        )}
      </div>

      <div className="flex flex-col sm:flex-row gap-3 w-full sm:justify-end">
        <div className="flex gap-2 w-full sm:w-auto">
          <Input
            aria-label={t("pages.loyaltyPointsPage.dateFrom")}
            type="date"
            variant="faded"
            value={query.date_from || ""}
            max={query.date_to || undefined}
            onChange={(e) =>
              onQueryChange({ page: 1, date_from: e.target.value })
            }
            classNames={{
              base: "max-w-40 shadow-sm",
            }}
          />
          <Input
            aria-label={t("pages.loyaltyPointsPage.dateTo")}
            type="date"
            variant="faded"
            value={query.date_to || ""}
            min={query.date_from || undefined}
            onChange={(e) =>
              onQueryChange({ page: 1, date_to: e.target.value })
            }
            classNames={{
              base: "max-w-40 shadow-sm",
            }}
          />
        </div>
        <Select
          aria-label={t("select_type")}
          placeholder={t("select_type")}
          selectedKeys={[query.type || ""]}
          onChange={(e) => onQueryChange({ page: 1, type: e.target.value })}
          variant="faded"
          classNames={{
            base: "max-w-32 shadow-sm",
          }}
        >
          {typeOptions.map((option) => (
            <SelectItem key={option.value} textValue={option.label}>
              {option.label}
            </SelectItem>
          ))}
        </Select>
      </div>
    </div>
  );

  return (
    <div className="w-full mt-4">
      <Table
        aria-label={t("pages.loyaltyPointsPage.table.title")}
        topContent={topContent}
        classNames={{
          wrapper:
            "p-4 sm:p-6 shadow-md border border-divider/40 rounded-2xl bg-content1",
          th: "bg-transparent text-xs font-bold uppercase tracking-wider text-foreground/60 border-b border-divider pb-3",
          td: "py-4 border-b border-divider/30 text-sm",
        }}
        bottomContent={
          totalPages > 1 ? (
            <div className="flex w-full justify-center mt-4">
              <Pagination
                isCompact
                showControls
                color="primary"
                page={page}
                total={totalPages}
                onChange={(page) => onQueryChange({ page })}
              />
            </div>
          ) : null
        }
      >
        <TableHeader columns={columns}>
          {(column) => (
            <TableColumn key={column.key}>{column.label}</TableColumn>
          )}
        </TableHeader>
        <TableBody
          items={isLoading ? Array.from({ length: 5 }).map((_, i) => ({ id: `loading-${i}`, isSkeleton: true } as unknown as LoyaltyPointTransaction)) : transactions}
          loadingContent={null}
          isLoading={isLoading}
          emptyContent={t("no_transactions_found")}
        >
          {(item: LoyaltyPointTransaction) => (
            <TableRow
              key={item.id}
              className="hover:bg-content2/30 transition-colors"
            >
              {(columnKey) => (
                <TableCell>
                  {(item as any).isSkeleton ? (
                    <Skeleton className="h-5 w-4/5 rounded-md" />
                  ) : (
                    renderCell(item, columnKey as string)
                  )}
                </TableCell>
              )}
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  );
};

export default LoyaltyPointsTable;
