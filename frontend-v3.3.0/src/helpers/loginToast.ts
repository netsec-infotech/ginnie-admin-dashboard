import i18n from "../../i18n";

export const getLoginSuccessToast = (name?: string) => {
  const firstName = name?.trim().split(/\s+/)[0];

  return {
    title: firstName
      ? i18n.t("login_modal.login_success_title", { name: firstName })
      : i18n.t("login_modal.welcome_title"),
    description: i18n.t("login_modal.login_success_toast"),
    color: "success" as const,
  };
};
