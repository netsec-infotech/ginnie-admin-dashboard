// src/components/WishlistCard.tsx
import React from "react";
import {
  Card,
  CardBody,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  Button,
  Spinner,
  Tooltip,
} from "@heroui/react";
import { Bookmark, MoreVertical, Pencil, Trash2 } from "lucide-react";

// Make sure to import the type for wishlist
import { WishTitle } from "@/types/ApiResponse";
import { useTranslation } from "react-i18next";

interface WishlistCardProps {
  key?: number | string;
  wishlist: WishTitle;
  selectedWishlist?: WishTitle | null;
  loading: { deleting: string | null };
  fetchWishlistDetails: (id: string, forceFetch?: boolean) => void;

  setEditingWishlist: (data: { id: string | number; title: string }) => void;
  onOpen: () => void;
  confirmDelete: (id: string, title: string) => void;
}

const WishlistCard: React.FC<WishlistCardProps> = ({
  wishlist,
  selectedWishlist,
  loading,
  fetchWishlistDetails,
  setEditingWishlist,
  onOpen,
  confirmDelete,
}) => {
  const isSelected = selectedWishlist?.id === wishlist.id;
  const isDeleting = loading.deleting === wishlist.id.toString();

  const { t } = useTranslation();

  return (
    <Card
      as={"div"}
      isPressable
      shadow="none"
      aria-current={isSelected ? "true" : undefined}
      className={`w-full min-w-56 cursor-pointer border transition-colors duration-200 lg:min-w-0 ${
        isSelected
          ? "border-primary/40 bg-primary/10"
          : "border-divider bg-content1 hover:border-primary/30 hover:bg-default-100/60"
      }`}
      onPress={() => fetchWishlistDetails(wishlist.id.toString())}
    >
      <CardBody className="px-3 py-3">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-3 flex-1 min-w-0">
            <div
              className={`flex size-9 shrink-0 items-center justify-center rounded-xl ${
                isSelected
                  ? "bg-primary text-primary-foreground"
                  : "bg-default-100 text-default-500"
              }`}
            >
              <Bookmark
                className={`size-4 ${isSelected ? "fill-current" : ""}`}
              />
            </div>

            <div className="min-w-0 flex-1">
              <h3 className="truncate text-sm font-semibold text-foreground">
                {wishlist.title}
              </h3>
              <p
                className={`mt-0.5 text-xs ${
                  isSelected ? "text-primary" : "text-default-500"
                }`}
              >
                {t("products_count", { count: wishlist.items_count || 0 })}
              </p>
            </div>
          </div>

          <Dropdown placement="bottom-end">
            <DropdownTrigger>
              <Button
                isIconOnly
                variant="light"
                size="sm"
                aria-label={t("wishlist.actions")}
                title={t("wishlist.actions")}
                className="relative size-8 min-w-8 text-default-500"
              >
                <Tooltip
                  content={t("wishlist.actions")}
                  placement="top"
                  delay={250}
                  closeDelay={0}
                  showArrow
                >
                  <span className="absolute inset-0 flex items-center justify-center">
                    <MoreVertical className="w-4 h-4" />
                  </span>
                </Tooltip>
              </Button>
            </DropdownTrigger>
            <DropdownMenu aria-label="Wishlist actions">
              <DropdownItem
                key="edit"
                textValue={t("edit")}
                startContent={<Pencil className="w-4 h-4" />}
                onPress={() => {
                  setEditingWishlist({
                    id: wishlist.id,
                    title: wishlist.title,
                  });
                  onOpen();
                }}
              >
                {t("edit")}
              </DropdownItem>
              <DropdownItem
                key="delete"
                textValue={t("delete")}
                color="danger"
                startContent={
                  isDeleting ? (
                    <Spinner size="sm" />
                  ) : (
                    <Trash2 className="w-4 h-4" />
                  )
                }
                onPress={() => {
                  confirmDelete(wishlist.id.toString(), wishlist.title);
                }}
                isReadOnly={isDeleting}
              >
                {t("delete")}
              </DropdownItem>
            </DropdownMenu>
          </Dropdown>
        </div>
      </CardBody>
    </Card>
  );
};

export default WishlistCard;
