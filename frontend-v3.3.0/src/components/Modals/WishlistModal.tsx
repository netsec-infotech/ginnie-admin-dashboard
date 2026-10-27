import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Button,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Spinner,
  useDisclosure,
} from "@heroui/react";
import {
  Bookmark,
  BookmarkPlus,
  Check,
  Sparkles,
  Trash2,
  X,
} from "lucide-react";
import {
  CreateWishListWithItems,
  deleteWishlistById,
  deleteWishlistItemById,
  getAllWishlistTitles,
} from "@/routes/api";
import { FavoriteItem, WishTitle } from "@/types/ApiResponse";
import { useTranslation } from "react-i18next";
import { useRouter } from "next/router";
import ConfirmationModal from "./ConfirmationModal";
import { updateDataOnAuth } from "@/helpers/updators";

interface WishlistModalProps {
  isOpen: boolean;
  onClose: () => void;
  productId: number;
  productVariantId?: number;
  storeId: number;
  favorite: FavoriteItem[] | null;
}

const WishlistModal = ({
  isOpen,
  onClose,
  productId,
  productVariantId,
  storeId,
  favorite = null,
}: WishlistModalProps) => {
  const { t } = useTranslation();
  const router = useRouter();
  const {
    onClose: onConfirmationModalClose,
    onOpen: onConfirmationModalOpen,
    isOpen: isConfirmationModalOpen,
  } = useDisclosure();

  const [wishlists, setWishlists] = useState<WishTitle[]>([]);
  const [newListTitle, setNewListTitle] = useState("");
  const [selectedWishlistId, setSelectedWishlistId] = useState("");
  const [isFetching, setIsFetching] = useState(false);
  const [isCreating, setIsCreating] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [wishlistItemToRemove, setWishlistItemToRemove] =
    useState<WishTitle | null>(null);
  const [isRemoveItemFromWishlist, setIsRemoveItemFromWishlist] = useState("");
  const [isDeletingWishlist, setIsDeletingWishlist] = useState<number | null>(
    null,
  );
  const [wishlistToDelete, setWishlistToDelete] = useState<WishTitle | null>(
    null,
  );
  const [removedWishlistIds, setRemovedWishlistIds] = useState<Set<string>>(
    new Set(),
  );
  const [error, setError] = useState("");

  const isInWishlist = useCallback(
    (wishlistId: number | string) => {
      const id = wishlistId.toString();

      return (
        !removedWishlistIds.has(id) &&
        Array.isArray(favorite) &&
        favorite.some((item) => item.wishlist_id.toString() === id)
      );
    },
    [favorite, removedWishlistIds],
  );

  const selectedWishlist = useMemo(
    () =>
      wishlists.find(
        (wishlist) => wishlist.id.toString() === selectedWishlistId,
      ),
    [selectedWishlistId, wishlists],
  );
  const selectedWishlistHasItem = selectedWishlist
    ? isInWishlist(selectedWishlist.id)
    : false;
  const isMutating =
    isCreating ||
    isSaving ||
    isDeletingWishlist !== null ||
    Boolean(isRemoveItemFromWishlist);

  useEffect(() => {
    if (wishlists.length === 0 || selectedWishlistId) return;

    const persistedSelection =
      wishlists.find((wishlist) => isInWishlist(wishlist.id)) ?? wishlists[0];

    setSelectedWishlistId(persistedSelection.id.toString());
  }, [isInWishlist, selectedWishlistId, wishlists]);

  const fetchWishlists = useCallback(async () => {
    setIsFetching(true);
    setError("");

    try {
      const response = await getAllWishlistTitles();
      if (response.success && response.data) {
        setWishlists([response.data].flat());
      } else {
        setWishlists([]);
        setError(response.message || t("wishlist.failed_fetch_wishlists"));
      }
    } catch {
      setWishlists([]);
      setError(t("wishlist.failed_fetch_wishlists"));
    } finally {
      setIsFetching(false);
    }
  }, [t]);

  useEffect(() => {
    if (!isOpen) return;

    setError("");
    setNewListTitle("");
    setSelectedWishlistId("");
    setRemovedWishlistIds(new Set());
    setWishlistItemToRemove(null);
    setWishlistToDelete(null);
    void fetchWishlists();
  }, [fetchWishlists, isOpen]);

  const refreshProductWishlistState = () => {
    updateDataOnAuth();
  };

  const refreshCurrentRoute = async (shallow = true) => {
    await router.push(
      { pathname: router.pathname, query: router.query },
      undefined,
      { scroll: false, shallow },
    );
  };

  const handleCreateWishlist = async () => {
    const title = newListTitle.trim();

    if (!title) {
      setError(t("wishlist.enter_wishlist_name"));
      return;
    }

    setIsCreating(true);
    setError("");

    try {
      const response = await CreateWishListWithItems({
        wishlist_title: title,
        product_id: productId,
        product_variant_id: productVariantId || null,
        store_id: storeId,
      });

      if (response.success) {
        refreshProductWishlistState();
        await refreshCurrentRoute();
        onClose();
      } else {
        setError(response.message || t("wishlist.failed_create_wishlist"));
      }
    } catch {
      setError(t("wishlist.failed_create_wishlist"));
    } finally {
      setIsCreating(false);
    }
  };

  const handleSaveToSelectedWishlist = async () => {
    if (!selectedWishlist) {
      setError(t("wishlist.select_wishlist"));
      return;
    }

    setIsSaving(true);
    setError("");

    try {
      const response = await CreateWishListWithItems({
        wishlist_title: selectedWishlist.title,
        product_id: productId,
        product_variant_id: productVariantId || null,
        store_id: storeId,
      });

      if (response.success) {
        refreshProductWishlistState();
        await refreshCurrentRoute();
        onClose();
      } else {
        setError(response.message || t("wishlist.failed_add_to_wishlist"));
      }
    } catch {
      setError(t("wishlist.failed_add_to_wishlist"));
    } finally {
      setIsSaving(false);
    }
  };

  const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "Enter" && !isCreating && newListTitle.trim()) {
      event.preventDefault();
      void handleCreateWishlist();
    }
  };

  const getFavoriteItemByWishlist = (wishlistId: number | string) =>
    Array.isArray(favorite)
      ? favorite.find(
          (item) => item.wishlist_id.toString() === wishlistId.toString(),
        )
      : undefined;

  const handleConfirmDeleteWishlist = async () => {
    if (!wishlistToDelete) return;
    const id = wishlistToDelete.id;

    onConfirmationModalClose();
    setIsDeletingWishlist(id);

    try {
      const response = await deleteWishlistById(id.toString());
      if (response.success) {
        refreshProductWishlistState();
        await refreshCurrentRoute(false);
        setWishlists((current) =>
          current.filter((wishlist) => wishlist.id !== id),
        );
        if (selectedWishlistId === id.toString()) {
          setSelectedWishlistId("");
        }
      } else {
        setError(response.message || t("wishlist.failed_delete_wishlist"));
      }
    } catch {
      setError(t("wishlist.failed_delete_wishlist"));
    } finally {
      setWishlistToDelete(null);
      setIsDeletingWishlist(null);
    }
  };

  const handleConfirmRemoveItem = async () => {
    if (!wishlistItemToRemove) return;

    const wishlistId = wishlistItemToRemove.id.toString();
    const favoriteItem = getFavoriteItemByWishlist(wishlistItemToRemove.id);

    if (!favoriteItem) {
      setError(t("item_not_found"));
      return;
    }

    onConfirmationModalClose();
    setIsRemoveItemFromWishlist(wishlistId);

    try {
      const response = await deleteWishlistItemById(favoriteItem.id);
      if (response.success) {
        setRemovedWishlistIds((current) => new Set(current).add(wishlistId));
        setWishlists((current) =>
          current.map((wishlist) =>
            wishlist.id === wishlistItemToRemove.id
              ? {
                  ...wishlist,
                  items_count: Math.max(0, wishlist.items_count - 1),
                }
              : wishlist,
          ),
        );
        refreshProductWishlistState();
        await refreshCurrentRoute();
      } else {
        setError(response.message || t("wishlist.failed_remove_item"));
      }
    } catch {
      setError(t("wishlist.failed_remove_item"));
    } finally {
      setWishlistItemToRemove(null);
      setIsRemoveItemFromWishlist("");
    }
  };

  const handleConfirm = async () => {
    if (wishlistToDelete) return handleConfirmDeleteWishlist();
    if (wishlistItemToRemove) return handleConfirmRemoveItem();
  };

  return (
    <>
      <Modal
        isOpen={isOpen}
        onClose={() => {
          if (!isMutating) onClose();
        }}
        isDismissable={!isMutating}
        isKeyboardDismissDisabled={isMutating}
        placement="center"
        scrollBehavior="inside"
        size="lg"
        backdrop="blur"
        classNames={{
          base: "mx-3 max-h-[calc(100dvh-1.5rem)] sm:mx-0",
          closeButton:
            "top-4 end-4 text-default-500 hover:bg-default-100 hover:text-foreground",
        }}
      >
        <ModalContent>
          <ModalHeader className="flex items-center gap-3 border-b border-divider px-5 py-4 pe-14 sm:px-6">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary dark:bg-primary/15">
              <Bookmark className="h-5 w-5" />
            </span>
            <h2 className="text-lg font-semibold text-foreground">
              {t("wishlist.save_to_wishlist")}
            </h2>
          </ModalHeader>

          <ModalBody className="gap-5 px-5 py-5 sm:px-6">
            {error && (
              <div
                role="alert"
                className="flex items-start justify-between gap-3 rounded-xl border border-danger-200 bg-danger-50 px-3 py-2.5 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300"
              >
                <span className="pt-0.5">{error}</span>
                <Button
                  isIconOnly
                  aria-label={t("close")}
                  variant="light"
                  size="sm"
                  onPress={() => setError("")}
                  className="h-7 min-w-7 text-danger"
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            )}

            <section
              aria-labelledby="create-wishlist-heading"
              className="rounded-2xl border border-primary-100 bg-primary-50/60 p-4 dark:border-primary-500/20 dark:bg-primary-500/10"
            >
              <div
                id="create-wishlist-heading"
                className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground"
              >
                <Sparkles className="h-4 w-4 text-primary" />
                {t("wishlist.create_new_wishlist")}
              </div>
              <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                  aria-label={t("wishlist.create_new_wishlist")}
                  placeholder={t("wishlist.enter_wishlist_name")}
                  value={newListTitle}
                  onValueChange={setNewListTitle}
                  onKeyDown={handleKeyDown}
                  variant="bordered"
                  classNames={{
                    input: "text-sm",
                    inputWrapper:
                      "border-default-200 bg-background shadow-none group-data-[focus=true]:border-primary",
                  }}
                  startContent={
                    <BookmarkPlus className="h-4 w-4 text-default-400" />
                  }
                  isDisabled={isCreating}
                />
                <Button
                  color="primary"
                  onPress={handleCreateWishlist}
                  isLoading={isCreating}
                  className="min-w-24 font-semibold sm:self-stretch"
                  isDisabled={!newListTitle.trim() || isMutating}
                >
                  {isCreating ? t("creating") : t("create")}
                </Button>
              </div>
            </section>

            <Divider />

            <section aria-labelledby="select-wishlist-heading">
              <div className="mb-3 flex items-center justify-between gap-3">
                <div
                  id="select-wishlist-heading"
                  className="flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                  <Bookmark className="h-4 w-4 text-default-500" />
                  {t("wishlist.select_wishlist")}
                </div>
                {!isFetching && wishlists.length > 0 && (
                  <Chip  variant="flat" color="primary">
                    {wishlists.length}{" "}
                    {wishlists.length === 1 ? t("list") : t("lists")}
                  </Chip>
                )}
              </div>

              {isFetching ? (
                <div className="flex min-h-44 flex-col items-center justify-center gap-3 rounded-2xl border border-divider bg-default-50/60">
                  <Spinner color="primary" size="md" />
                  <span className="text-sm text-default-500">
                    {t("wishlist.loading_wishlists")}
                  </span>
                </div>
              ) : wishlists.length === 0 ? (
                <div className="flex min-h-44 flex-col items-center justify-center rounded-2xl border border-dashed border-default-300 px-6 text-center">
                  <span className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-default-100 text-default-400">
                    <Bookmark className="h-5 w-5" />
                  </span>
                  <p className="text-sm font-medium text-foreground">
                    {t("wishlist.no_wishlists_found")}
                  </p>
                  <p className="mt-1 text-xs text-default-500">
                    {t("wishlist.create_first_wishlist")}
                  </p>
                </div>
              ) : (
                <div
                  role="radiogroup"
                  aria-label={t("wishlist.select_wishlist")}
                  className="max-h-72 space-y-2 overflow-y-auto pe-1"
                >
                  {wishlists.map((wishlist) => {
                    const id = wishlist.id.toString();
                    const isSelected = selectedWishlistId === id;
                    const alreadySaved = isInWishlist(wishlist.id);
                    const isRemoving = isRemoveItemFromWishlist === id;

                    return (
                      <div
                        key={id}
                        className={`flex items-center gap-2 rounded-xl border p-2 transition-colors ${
                          isSelected
                            ? "border-primary bg-primary-50/70 dark:bg-primary/10"
                            : "border-divider bg-content1 hover:border-default-300 hover:bg-default-50"
                        }`}
                      >
                        <label className="relative flex min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-lg p-1.5 text-start outline-none has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary has-[:focus-visible]:ring-offset-2">
                          <input
                            type="radio"
                            name="wishlist-selection"
                            value={id}
                            checked={isSelected}
                            disabled={isMutating}
                            onChange={() => setSelectedWishlistId(id)}
                            className="sr-only"
                          />
                          <span
                            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${
                              alreadySaved
                                ? "bg-success-100 text-success-600 dark:bg-success/15 dark:text-success-400"
                                : "bg-primary-100 text-primary-600 dark:bg-primary/15 dark:text-primary-400"
                            }`}
                          >
                            <Bookmark className="h-4 w-4 fill-current" />
                          </span>
                          <span className="min-w-0 flex-1">
                            <span className="flex items-center gap-2">
                              <span className="truncate text-sm font-semibold text-foreground">
                                {wishlist.title}
                              </span>
                              {alreadySaved && (
                                <Chip
                                  size="sm"
                                  variant="flat"
                                  color="success"
                                  className="h-5 shrink-0 text-[11px]"
                                >
                                  {t("already_in_list")}
                                </Chip>
                              )}
                            </span>
                            <span className="mt-0.5 block text-xs text-default-500">
                              {wishlist.items_count}{" "}
                              {wishlist.items_count === 1
                                ? t("item")
                                : t("items")}
                            </span>
                          </span>
                          <span
                            aria-hidden="true"
                            className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 ${
                              isSelected
                                ? "border-primary bg-primary text-primary-foreground"
                                : "border-default-300"
                            }`}
                          >
                            {isSelected && (
                              <Check className="h-3 w-3" strokeWidth={3} />
                            )}
                          </span>
                        </label>

                        {alreadySaved && (
                          <Button
                            isIconOnly
                            aria-label={t("remove_item")}
                            title={t("remove_item")}
                            size="sm"
                            variant="light"
                            color="danger"
                            isLoading={isRemoving}
                            isDisabled={isMutating && !isRemoving}
                            onPress={() => {
                              setWishlistToDelete(null);
                              setWishlistItemToRemove(wishlist);
                              onConfirmationModalOpen();
                            }}
                            className="shrink-0"
                          >
                            <X className="h-4 w-4" />
                          </Button>
                        )}

                        {wishlist.title !== "Favorite" && (
                          <Button
                            isIconOnly
                            aria-label={t("wishlist.delete_wishlist")}
                            title={t("wishlist.delete_wishlist")}
                            size="sm"
                            variant="light"
                            color="danger"
                            isLoading={isDeletingWishlist === wishlist.id}
                            isDisabled={
                              isMutating && isDeletingWishlist !== wishlist.id
                            }
                            onPress={() => {
                              setWishlistItemToRemove(null);
                              setWishlistToDelete(wishlist);
                              onConfirmationModalOpen();
                            }}
                            className="shrink-0"
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        )}
                      </div>
                    );
                  })}
                </div>
              )}
            </section>
          </ModalBody>

          <ModalFooter className="flex-row items-center justify-between border-t border-divider px-5 py-4 sm:px-6">
            <Button
              variant="light"
              onPress={onClose}
              className="font-medium"
              isDisabled={isMutating}
            >
              {t("cancel")}
            </Button>
            {wishlists.length > 0 && (
              <Button
                color="primary"
                onPress={handleSaveToSelectedWishlist}
                isLoading={isSaving}
                startContent={
                  !isSaving && !selectedWishlistHasItem ? (
                    <BookmarkPlus className="h-4 w-4" />
                  ) : undefined
                }
                className="px-5 font-semibold"
                isDisabled={
                  !selectedWishlist || isMutating || selectedWishlistHasItem
                }
              >
                {selectedWishlistHasItem
                  ? t("already_in_list")
                  : isSaving
                    ? t("saving")
                    : t("wishlist.save_to_wishlist")}
              </Button>
            )}
          </ModalFooter>
        </ModalContent>
      </Modal>

      <ConfirmationModal
        isOpen={isConfirmationModalOpen}
        onClose={() => {
          onConfirmationModalClose();
          setWishlistToDelete(null);
          setWishlistItemToRemove(null);
        }}
        onConfirm={handleConfirm}
        title={
          wishlistToDelete
            ? t("wishlist.confirm_delete_wishlist")
            : t("wishlist.confirm_remove_item")
        }
        alertTitle={
          wishlistToDelete
            ? t("wishlist.confirm_delete_wishlist")
            : t("wishlist.confirm_remove_item")
        }
      />
    </>
  );
};

export default WishlistModal;
