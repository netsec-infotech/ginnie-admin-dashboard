import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import PageHeader from "@/components/custom/PageHeader";
import LoyaltyPointsCard from "@/components/LoyaltyPoints/LoyaltyPointsCard";
import LoyaltyPointsTable from "@/components/Tables/LoyaltyPointsTable";
import { getAccessTokenFromContext } from "@/helpers/auth";
import {
  getPageFromUrl,
  getQueryParamFromUrl,
  isSSR,
} from "@/helpers/getters";
import UserLayout from "@/layouts/UserLayout";
import { getLoyaltyPointsTransactions, getSettings } from "@/routes/api";
import {
  LoyaltyPointsTransactionsData,
  LoyaltyTransactionQueryArgs,
} from "@/types/ApiResponse";
import { GetServerSideProps } from "next";
import { useRouter } from "next/router";
import { useState } from "react";
import useSWR from "swr";
import { NextPageWithLayout } from "@/types";
import { loadTranslations } from "../../../../i18n";
import PageHead from "@/SEO/PageHead";
import { useTranslation } from "react-i18next";

type LoyaltyPointsPageProps = {
  initialData: LoyaltyPointsTransactionsData | null;
  initialQuery: LoyaltyTransactionQueryArgs;
  error?: string;
};

const perPage = 10;

/** ISO date-part comparison ("YYYY-MM-DD" strings compare lexicographically). */
const isWithinRange = (createdAt: string, from?: string, to?: string) => {
  const day = createdAt.slice(0, 10);
  if (from && day < from) return false;
  if (to && day > to) return false;
  return true;
};

const LoyaltyPointsPage: NextPageWithLayout<LoyaltyPointsPageProps> = ({
  initialData = null,
  initialQuery = {},
}) => {
  const { t } = useTranslation();
  const router = useRouter();

  const [query, setQuery] = useState<LoyaltyTransactionQueryArgs>(() => ({
    page: initialQuery?.page ?? getPageFromUrl(),
    type: initialQuery?.type || getQueryParamFromUrl("type") || "",
    date_from:
      initialQuery?.date_from || getQueryParamFromUrl("date_from") || "",
    date_to: initialQuery?.date_to || getQueryParamFromUrl("date_to") || "",
  }));

  const { data, isLoading, isValidating, mutate } = useSWR(
    [{ ...query, key: "loyalty-points" }],
    ([q]) =>
      getLoyaltyPointsTransactions({
        page: q.page,
        per_page: perPage,
        type: q.type,
        date_from: q.date_from,
        date_to: q.date_to,
      }),
    {
      fallbackData: initialData
        ? { success: true, message: "", data: initialData }
        : undefined,
      revalidateOnFocus: false,
      revalidateOnMount: !isSSR(),
      keepPreviousData: true,
    }
  );

  const handleQueryChange = (args: LoyaltyTransactionQueryArgs) => {
    setQuery((prev) => ({ ...prev, page: args.page || 1, ...args }));
    const filteredArgs = Object.fromEntries(
      Object.entries({ ...router.query, ...args }).filter(
        ([, value]) => value !== 0 && value !== "" && value != null
      )
    );
    router.push({ pathname: router.pathname, query: filteredArgs }, undefined, {
      shallow: true,
    });
  };

  const summary = data?.success ? (data.data ?? null) : null;
  const disabled = summary ? summary.is_enabled === false : false;
  // The backend doesn't honor date_from/date_to yet (verified 2026-08-06);
  // the params are sent regardless so this becomes a no-op once it does, and
  // until then the current page of rows is filtered client-side.
  const transactions = (data?.data?.transactions ?? []).filter((tx) =>
    isWithinRange(tx.created_at, query.date_from, query.date_to)
  );
  const total = data?.data?.pagination?.total ?? 0;

  return (
    <>
      <MyBreadcrumbs
        breadcrumbs={[
          {
            href: "/my-account/loyalty-points",
            label: t("pageTitle.loyalty-points"),
          },
        ]}
      />
      <PageHead pageTitle={t("pageTitle.loyalty-points")} noindex />

      <UserLayout activeTab="loyalty-points">
        <div className="w-full">
          {/* Header */}
          <div className="flex items-center gap-4 justify-between">
            <PageHeader
              title={t("pages.loyaltyPointsPage.header.title")}
              subtitle={t("pages.loyaltyPointsPage.header.subtitle")}
            />
            {isSSR() && (
              <div className="text-xs sm:text-sm text-gray-500">
                {t("pages.loyaltyPointsPage.totalFound", { total })}
              </div>
            )}
          </div>

          {disabled ? (
            <div className="w-full rounded-medium bg-content2 text-foreground/70 text-sm p-6 text-center">
              {t("pages.loyaltyPointsPage.notEnabled")}
            </div>
          ) : (
            <div className="w-full flex flex-col gap-2">
              <LoyaltyPointsCard
                summary={summary}
                loading={isLoading && !summary}
              />

              {/* Table */}
              <LoyaltyPointsTable
                transactions={transactions}
                total={total}
                page={query.page || 1}
                per_page={perPage}
                query={query}
                // isLoading misses same-key revalidations (the refresh
                // button's mutate()); isValidating covers those.
                isLoading={isLoading || isValidating}
                tableTitle={t("pages.loyaltyPointsPage.table.title")}
                onQueryChange={handleQueryChange}
                onRefresh={() => mutate()}
              />
            </div>
          )}
        </div>
      </UserLayout>
    </>
  );
};

export const getServerSideProps: GetServerSideProps | undefined = isSSR()
  ? async (context) => {
      try {
        const access_token = (await getAccessTokenFromContext(context)) || "";
        if (!access_token) {
          return {
            redirect: {
              destination: "/",
              permanent: false,
            },
          };
        }
        await loadTranslations(context);

        const {
          page = "1",
          type = "",
          date_from = "",
          date_to = "",
        } = context.query;
        const asString = (value: string | string[] | undefined) =>
          typeof value === "string" ? value : "";

        const [transactionsRes, settings] = await Promise.all([
          getLoyaltyPointsTransactions({
            page: asString(page) || "1",
            per_page: perPage,
            type: asString(type),
            date_from: asString(date_from),
            date_to: asString(date_to),
            access_token,
          }),
          getSettings(),
        ]);

        return {
          props: {
            initialData: transactionsRes.success
              ? (transactionsRes.data ?? null)
              : null,
            initialSettings: settings.data,
            initialQuery: {
              page: parseInt(asString(page) || "1"),
              type: asString(type),
              date_from: asString(date_from),
              date_to: asString(date_to),
            },
            ...(transactionsRes.success
              ? {}
              : {
                  error:
                    transactionsRes.message ||
                    "Failed to fetch loyalty point transactions",
                }),
          },
        };
      } catch (error) {
        console.error("Error fetching loyalty points:", error);

        return {
          props: {
            initialData: null,
            initialSettings: null,
            initialQuery: {},
          },
        };
      }
    }
  : undefined;

export default LoyaltyPointsPage;
