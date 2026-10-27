import {
  Avatar,
  Dropdown,
  DropdownItem,
  DropdownMenu,
  DropdownTrigger,
  Tooltip,
  useDisclosure,
} from "@heroui/react";
import { useRouter } from "next/router";
import { FC, Key, ReactNode, useEffect } from "react";
import LogoutModal from "./Modals/LogoutModal";
import { useSelector } from "react-redux";
import { RootState } from "@/lib/redux/store";
import { staticProfileImage } from "@/config/constants";
import { useTranslation } from "react-i18next";
import { useSettings } from "@/contexts/SettingsContext";
import {
  User,
  Package,
  MapPin,
  Wallet,
  Receipt,
  LogOut,
  Settings,
  Gift,
  Bell,
  ShieldAlert,
} from "lucide-react";
import {
  getVerificationMessageKey,
  getVerificationStatus,
} from "@/helpers/verification";
import {
  NOTIFICATIONS_UPDATED_EVENT,
  useUnreadNotificationCount,
} from "@/hooks/useUnreadNotificationCount";

type ProfileMenuItem = {
  key: string;
  icon: ReactNode;
  text: string;
  className?: string;
  color?: "danger";
  isProfile?: boolean;
};

const ProfileBtn: FC = () => {
  const router = useRouter();
  const { isOpen, onOpen, onClose } = useDisclosure();
  const userData = useSelector((state: RootState) => state.auth.user);
  const { t } = useTranslation();
  const { systemSettings } = useSettings();
  const { unreadCount, refreshUnreadCount } = useUnreadNotificationCount();
  const unreadLabel = unreadCount >= 10 ? "9+" : String(unreadCount);

  useEffect(() => {
    window.addEventListener(NOTIFICATIONS_UPDATED_EVENT, refreshUnreadCount);
    return () =>
      window.removeEventListener(
        NOTIFICATIONS_UPDATED_EVENT,
        refreshUnreadCount,
      );
  }, [refreshUnreadCount]);

  const handleAction = (key: Key) => {
    const route = key.toString();
    if (route === "logout") {
      onOpen();
    } else {
      router.push(route);
    }
  };

  /**
   * Checkout requires a verified mobile number, but the server only
   * says so once the order is submitted. Surfacing it here means the customer
   * can act on it before they reach payment.
   */
  const verification = getVerificationStatus(userData);
  const pendingKey = getVerificationMessageKey(verification);

  const verificationLabel = pendingKey
    ? t("profileBtn.verification.mobile")
    : "";

  const profileMenuItems: ProfileMenuItem[] = [
    {
      key: "/my-account/",
      icon: <User size={16} />,
      text: `${t("profileBtn.signedInAs")} ${userData?.email || ""}`,
      isProfile: true,
    },
    // Top of the list, directly under the identity row — it's the one item
    // that blocks the customer from completing an order.
    ...(verification.isPending
      ? [
          {
            // Trailing slash keeps this key distinct from the "My Account"
            // item below — DropdownMenu keys must be unique — while still
            // routing to the same page.
            key: "/my-account/#verification",
            icon: <ShieldAlert size={16} className="text-warning" />,
            text: verificationLabel,
            className: "text-xs font-semibold text-warning",
          },
        ]
      : []),
    {
      key: "/my-account",
      icon: <Settings size={16} />,
      text: t("profileBtn.myAccount"),
    },
    {
      key: "/my-account/orders",
      icon: <Package size={16} />,
      text: t("profileBtn.myOrders"),
    },
    ...(systemSettings?.referEarnStatus !== false
      ? [
          {
            key: "/my-account/refer-and-earn",
            icon: <Gift size={16} />,
            text: t("profileBtn.referAndEarn"),
          },
        ]
      : []),
    {
      key: "/my-account/addresses",
      icon: <MapPin size={16} />,
      text: t("profileBtn.addresses"),
    },
    {
      key: "/my-account/wallet",
      icon: <Wallet size={16} />,
      text: t("profileBtn.wallet"),
    },
    {
      key: "/my-account/transactions",
      icon: <Receipt size={16} />,
      text: t("profileBtn.transactions"),
    },
    {
      key: "/my-account/notifications",
      icon: <Bell size={16} />,
      text: t("profileBtn.notifications"),
    },
    {
      key: "logout",
      icon: <LogOut size={16} className="text-danger-400" />,
      text: t("profileBtn.logout"),
      className: "text-xs text-danger-400",
      color: "danger",
    },
  ];

  return (
    <div className="flex items-center gap-4">
      {/* The badge lives OUTSIDE <Dropdown>, positioned against this wrapper.
          Dropdown does `const [menuTrigger, menu] = Children.toArray(children)`
          — it consumes exactly two children by position, so a third would be
          silently discarded. */}
      <div className="relative inline-flex">
        <Dropdown placement="bottom-end">
          {/* Avatar must be the trigger's DIRECT child. DropdownTrigger
              forwards a ref onto its child and hands it to a ResizeObserver;
              nesting Trigger > Tooltip > Badge > Avatar put a component there
              instead of a DOM node, and observe() threw
              "parameter 1 is not of type 'Element'". */}
          <DropdownTrigger>
            <Avatar
              isBordered
              as="button"
              size="sm"
              src={userData?.profile_image || staticProfileImage}
              className="transition-transform cursor-pointer"
              classNames={{ base: "w-7 h-7" }}
              alt={userData?.name || "User Avatar"}
              aria-label={
                unreadCount > 0
                  ? `${userData?.name || "User Avatar"}, ${unreadLabel} ${t("profileBtn.unreadNotifications")}`
                  : userData?.name || "User Avatar"
              }
            />
          </DropdownTrigger>

          <DropdownMenu
            aria-label="Profile Actions"
            variant="flat"
            onAction={handleAction}
            classNames={{ list: "text-xs" }}
            items={profileMenuItems}
          >
            {(item) => (
              <DropdownItem
                key={item.key}
                startContent={item.icon}
                endContent={
                  item.key === "/my-account/notifications" &&
                  unreadCount > 0 ? (
                    <span className="min-w-5 rounded-full bg-primary px-1.5 py-0.5 text-center text-[10px] font-bold text-primary-foreground">
                      {unreadLabel}
                    </span>
                  ) : undefined
                }
                textValue={item.text}
                className={item.isProfile ? "h-14 gap-2" : undefined}
                color={item.color}
                classNames={{ title: item.className || "text-xs" }}
              >
                {item.isProfile ? (
                  <>
                    <p className="font-semibold">
                      {t("profileBtn.signedInAs")}
                    </p>
                    <p className="font-semibold truncate">{userData?.name}</p>
                  </>
                ) : (
                  <>
                    {item.text}
                    {item.key === "/my-account/notifications" &&
                      unreadCount > 0 && (
                        <span className="sr-only">
                          {`, ${unreadLabel} ${t("profileBtn.unreadNotifications")}`}
                        </span>
                      )}
                  </>
                )}
              </DropdownItem>
            )}
          </DropdownMenu>
        </Dropdown>

        {unreadCount > 0 && (
          <span
            className="pointer-events-none absolute -top-1 -end-1 z-10 h-2.5 w-2.5 rounded-full bg-primary ring-2 ring-background"
            role="img"
            aria-label={t("profileBtn.unreadNotifications", {
              count: unreadCount,
              defaultValue: "Unread notifications",
            })}
            data-testid="profile-notification-dot"
          />
        )}

        {/* Sibling of the trigger, never an ancestor — see the note above. */}
        {verification.isPending && (
          <Tooltip content={verificationLabel} placement="bottom">
            <span
              className="pointer-events-auto absolute -bottom-0.5 -end-0.5 z-10 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-warning text-warning-foreground ring-1 ring-background"
              role="img"
              aria-label={verificationLabel}
              data-testid="profile-verification-badge"
            >
              <ShieldAlert size={9} />
            </span>
          </Tooltip>
        )}
      </div>

      <LogoutModal
        isOpen={isOpen}
        onClose={onClose}
        userName={userData?.name}
        userEmail={userData?.email}
        profileImg={userData?.profile_image || staticProfileImage}
      />
    </div>
  );
};

export default ProfileBtn;
