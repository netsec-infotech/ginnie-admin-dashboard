import { useSyncExternalStore } from "react";
import { useSelector } from "react-redux";
import { useTranslation } from "react-i18next";
import { Button, Spinner } from "@heroui/react";
import Link from "next/link";
import type { GetServerSideProps } from "next";
import type { RootState } from "@/lib/redux/store";
import type { NextPageWithLayout } from "@/types";
import MyBreadcrumbs from "@/components/custom/MyBreadcrumbs";
import UserLayout from "@/layouts/UserLayout";
import PageHead from "@/SEO/PageHead";
import SupportChatView from "@/views/SupportChatView";
import { isSSR } from "@/helpers/getters";
import { serverSideAuthGuard } from "@/guards/authGuard";
import { getSettings } from "@/routes/api";
import { loadTranslations } from "../../../../i18n";

const subscribeToHydration = () => () => {};
const getClientSnapshot = () => true;
const getServerSnapshot = () => false;

const SupportPage: NextPageWithLayout = () => {
  const { t } = useTranslation();
  // Redux may already be restored before React hydrates this page. Keep the
  // server and first browser render identical before reading its auth branch.
  const clientReady = useSyncExternalStore(
    subscribeToHydration,
    getClientSnapshot,
    getServerSnapshot,
  );
  const user = useSelector((state: RootState) => state.auth.user);
  const loggedIn = useSelector((state: RootState) => state.auth.isLoggedIn);
  const hydrated = useSelector(
    (state: RootState) => state._persist?.rehydrated,
  );
  return (
    <>
      <PageHead pageTitle={t("supportChat.title")} noindex />
      <MyBreadcrumbs
        breadcrumbs={[
          { href: "/my-account", label: t("userLayout.myAccount") },
          { href: "/my-account/support", label: t("supportChat.title") },
        ]}
      />
      <UserLayout activeTab="support">
        {!clientReady || !hydrated ? (
          <div className="flex min-h-80 items-center justify-center">
            <Spinner label={t("supportChat.loading")} />
          </div>
        ) : loggedIn && user?.id != null ? (
          <SupportChatView key={user.id} />
        ) : (
          <div className="rounded-2xl border border-divider bg-content1 p-8 text-center">
            <p className="mb-4">{t("supportChat.loginRequired")}</p>
            <Button as={Link} href="/" color="primary">
              {t("supportChat.goHome")}
            </Button>
          </div>
        )}
      </UserLayout>
    </>
  );
};

export const getServerSideProps: GetServerSideProps | undefined = isSSR()
  ? async (context) => {
      const guard = await serverSideAuthGuard(context);
      if (guard) return guard;
      await loadTranslations(context);
      const settings = await getSettings();
      return { props: { initialSettings: settings.data } };
    }
  : undefined;

export default SupportPage;
