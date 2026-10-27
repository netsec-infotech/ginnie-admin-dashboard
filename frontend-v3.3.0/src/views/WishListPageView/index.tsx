import React from "react";
import EmptyWishListState from "./EmptyWishListState";
import WishlistItemCard from "@/components/Cards/WishlistItemCard";
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Divider,
  Skeleton,
  Chip,
  useDisclosure,
  ScrollShadow,
} from "@heroui/react";
import { Bookmark, Plus, ShoppingBag } from "lucide-react";
import Link from "next/link";
import WishlistCard from "@/components/Cards/WishlistCard";
import WishListSkeleton from "@/components/Skeletons/WishListSkeleton";
import WishListErrorAlert from "./WishListErrorAlert";
import { Wishlist, WishTitle } from "@/types/ApiResponse";
import CreateWishListModal from "@/components/Modals/CreateWishListModal";
import { useTranslation } from "react-i18next";

interface ErrorState {
  message: string;
  type: "error" | "warning" | "info";
}

interface WishListPageViewProps {
  error: ErrorState | null;
  setError: React.Dispatch<React.SetStateAction<ErrorState | null>>;
  loading: {
    wishlists: boolean;
    wishlistDetails: boolean;
    updating: boolean;
    deleting: string | null;
    removingItem: number | null;
  };
  wishlists: WishTitle[];
  selectedWishlist: Wishlist | null;
  fetchWishlistDetails: (id: string, forceFetch?: boolean) => void;
  fetchWishlists: () => void;
  setEditingWishlist: React.Dispatch<
    React.SetStateAction<{ id: string | number; title: string } | null>
  >;
  onOpen: () => void;
  confirmDelete: (id: string, title: string) => void;
  handleRemoveItem: (id: number, forceFetch: boolean) => void;
}

const WishListPageView: React.FC<WishListPageViewProps> = ({
  error,
  setError,
  loading,
  wishlists,
  selectedWishlist,
  fetchWishlistDetails,
  fetchWishlists,
  setEditingWishlist,
  onOpen,
  confirmDelete,
  handleRemoveItem,
}) => {
  const {
    isOpen: isCreateWishListModalOpen,
    onClose: onCreateWishListModalClose,
    onOpen: onCreateWishListModalOpen,
  } = useDisclosure();
  const { t } = useTranslation();
  return (
    <div className="max-w-7xl mx-auto">
      <WishListErrorAlert error={error} setError={setError} />

      <div className="grid items-start gap-4 lg:grid-cols-12 lg:gap-6">
        {/* Wishlists Sidebar */}
        <div className="lg:col-span-4">
          <Card shadow="sm" className="overflow-hidden lg:sticky lg:top-24">
            <CardHeader className="p-4">
              <div className="flex items-center justify-between w-full">
                <div className="flex items-center gap-2">
                  <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Bookmark className="size-4" />
                  </div>
                  <h2 className="text-sm font-semibold">
                    {t("userLayout.myWishlists")}
                  </h2>
                </div>
                <Button
                  size="sm"
                  color="primary"
                  variant="flat"
                  startContent={<Plus className="w-4 h-4" />}
                  className="h-9 px-3 text-xs font-medium"
                  onPress={onCreateWishListModalOpen}
                >
                  {t("wishlist.new")}
                </Button>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="p-3">
              <div className="overflow-x-auto p-1 lg:max-h-[60vh] lg:overflow-y-auto">
                {loading.wishlists ? (
                  <WishListSkeleton />
                ) : wishlists.length === 0 ? (
                  <EmptyWishListState
                    icon={Bookmark}
                    title={t("wishlist.no_wishlists_found")}
                    description={t("wishlist.emptyListDescription")}
                    compact
                    action={
                      <Button
                        size="sm"
                        color="primary"
                        onPress={onCreateWishListModalOpen}
                        startContent={<Plus className="size-4" />}
                      >
                        {t("wishlist.new")}
                      </Button>
                    }
                  />
                ) : (
                  <div className="flex gap-2 pb-1 lg:flex-col">
                    {wishlists.map((wishlist) => (
                      <WishlistCard
                        key={wishlist.id}
                        wishlist={wishlist}
                        selectedWishlist={selectedWishlist}
                        loading={loading}
                        fetchWishlistDetails={fetchWishlistDetails}
                        setEditingWishlist={setEditingWishlist}
                        onOpen={onOpen}
                        confirmDelete={confirmDelete}
                      />
                    ))}
                  </div>
                )}
              </div>
            </CardBody>
          </Card>
        </div>

        {/* Wishlist Details */}
        <div className="lg:col-span-8">
          <Card shadow="sm" className="h-fit min-h-96 overflow-hidden">
            {selectedWishlist ? (
              <>
                <CardHeader className="p-4">
                  <div className="flex items-center justify-between w-full">
                    <div className="flex items-center gap-3">
                      <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                        <Bookmark className="size-4 fill-current" />
                      </div>
                      <div>
                        <h2 className="line-clamp-1 text-sm font-semibold">
                          {selectedWishlist.title}
                        </h2>
                        <p className="text-xs text-default-500">
                          {t("created")}{" "}
                          {new Date(
                            selectedWishlist.created_at
                          ).toLocaleDateString()}
                        </p>
                      </div>
                    </div>
                    <Chip
                      color="primary"
                      variant="flat"
                      size="sm"
                      className="shrink-0 text-xs"
                    >
                      {t("products_count", {
                        count: selectedWishlist.items?.length || 0,
                      })}
                    </Chip>
                  </div>
                </CardHeader>
                <Divider />
                <CardBody className="p-4">
                  <ScrollShadow className="max-h-[60vh] pr-2">
                    {loading.wishlistDetails ? (
                      <div className="grid gap-4">
                        {[1, 2, 3].map((i) => (
                          <Card
                            key={i}
                            shadow="none"
                            className="border border-divider"
                          >
                            <CardBody className="p-3">
                              <div className="flex gap-3">
                                <Skeleton className="rounded-lg w-15 h-15" />
                                <div className="flex-1 space-y-2">
                                  <Skeleton className="h-4 w-3/4 rounded" />
                                  <Skeleton className="h-3 w-1/2 rounded" />
                                  <Skeleton className="h-3 w-1/3 rounded" />
                                </div>
                              </div>
                            </CardBody>
                          </Card>
                        ))}
                      </div>
                    ) : selectedWishlist.items?.length === 0 ? (
                      <EmptyWishListState
                        icon={ShoppingBag}
                        title={t("wishlist_emptyTitle")}
                        description={t("wishlist_emptyDescription")}
                        action={
                          <Button
                            as={Link}
                            href="/"
                            color="primary"
                            variant="flat"
                            startContent={<ShoppingBag className="size-4" />}
                          >
                            {t("browse")}
                          </Button>
                        }
                      />
                    ) : (
                      <div className="grid gap-3">
                        {selectedWishlist.items?.map((item) => (
                          <WishlistItemCard
                            key={item.id}
                            item={item}
                            loading={loading}
                            handleRemoveItem={handleRemoveItem}
                            wishlists={wishlists}
                            fetchWishlists={fetchWishlists}
                            fetchWishlistDetails={fetchWishlistDetails}
                          />
                        ))}
                      </div>
                    )}
                  </ScrollShadow>
                </CardBody>
              </>
            ) : (
              <CardBody className="p-4">
                <EmptyWishListState
                  icon={Bookmark}
                  title={t("wishlist_selectTitle")}
                  description={t("wishlist_selectDescription")}
                />
              </CardBody>
            )}
          </Card>
        </div>
      </div>

      <CreateWishListModal
        isOpen={isCreateWishListModalOpen}
        onClose={onCreateWishListModalClose}
        onSuccess={() => {
          fetchWishlists();
        }}
      />
    </div>
  );
};

export default WishListPageView;
