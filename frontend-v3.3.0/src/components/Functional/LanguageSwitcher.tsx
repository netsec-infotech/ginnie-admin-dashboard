import { useTranslation } from "react-i18next";
import { useSelector } from "react-redux";
import {
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Button,
  addToast,
} from "@heroui/react";
import { ChevronDown } from "lucide-react";
import type { RootState } from "@/lib/redux/store";
import { switchLanguage } from "@/lib/localization/client";

interface LanguageSwitcherProps {
  triggerClassName?: string;
}

export default function LanguageSwitcher({ triggerClassName = "" }: LanguageSwitcherProps) {
  const { t } = useTranslation();
  const { languages, active, switchStatus } = useSelector(
    (state: RootState) => state.localization,
  );
  const selected = languages.find((language) => language.code === active.locale);

  return (
    <Dropdown
      size="sm"
      classNames={{
        trigger: "p-0 min-w-9 data-[hover=true]:bg-inherit",
        base: "text-xs",
        content: "min-w-4 text-xs",
      }}
    >
      <DropdownTrigger className="w-auto min-w-max shrink-0">
        <Button
          variant="light"
          isLoading={switchStatus === "loading"}
          aria-label={t("language_switcher.label")}
          className={`flex w-auto min-w-max shrink-0 items-center gap-2 whitespace-nowrap px-2 ${triggerClassName}`}
        >
          <span className="shrink-0">{selected?.native_name || selected?.name || active.locale}</span>
          <ChevronDown className="h-4 w-4 shrink-0" />
        </Button>
      </DropdownTrigger>
      <DropdownMenu
        aria-label={t("language_switcher.label")}
        selectionMode="single"
        selectedKeys={[active.locale]}
        disabledKeys={switchStatus === "loading" ? languages.map((item) => item.code) : []}
        onSelectionChange={(keys) => {
          const locale = Array.from(keys)[0];
          if (typeof locale !== "string") return;
          void switchLanguage(locale).then((succeeded) => {
            if (!succeeded) {
              addToast({ title: t("language_switcher.switch_failed"), color: "danger" });
            }
          });
        }}
      >
        {languages.map((language) => (
          <DropdownItem key={language.code} textValue={language.native_name || language.name}>
            {language.native_name || language.name}
          </DropdownItem>
        ))}
      </DropdownMenu>
    </Dropdown>
  );
}
