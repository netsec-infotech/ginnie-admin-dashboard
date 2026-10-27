import {
  Button,
  Card,
  CardBody,
  CardHeader,
  useDisclosure,
  Chip,
} from "@heroui/react";
import { MapPin, Plus, ChevronDown } from "lucide-react";
import { FC, useState, useEffect, useCallback, useMemo, useRef } from "react";
import { useDispatch, useSelector } from "react-redux";
import { RootState } from "@/lib/redux/store";
import useSWR, { mutate } from "swr";
import { setSelectedAddress } from "@/lib/redux/slices/checkoutSlice";
import AddressSelectionModal from "../Modals/AddressSelectionModal";
import { getAddresses } from "@/routes/api";
import { isEmptyCartMessage, updateCartData } from "@/helpers/updators";
import { confirmCartLocation } from "@/helpers/cartLocationConfirmation";
import { useTranslation } from "react-i18next";
import { getCookie } from "@/lib/cookies";
import { UserLocation } from "../Location/types/LocationAutoComplete.types";
import { Address } from "@/types/ApiResponse";

type AddressSectionProps = {
  onAddAddressModalOpen: () => void;
};

const AddressSection: FC<AddressSectionProps> = ({ onAddAddressModalOpen }) => {
  const { isOpen, onOpen, onClose, onOpenChange } = useDisclosure();
  const { t } = useTranslation();
  const { cartData, pendingImportedCart, deliveryLocationNeedsSelection } = useSelector((state: RootState) => state.cart);
  const zone_id = (pendingImportedCart ?? cartData)?.delivery_zone?.zone_id || "";

  const dispatch = useDispatch();
  const selectedAddress = useSelector(
    (state: RootState) => state.checkout.selectedAddress
  );

  const [tempSelectedId, setTempSelectedId] = useState<string>("");
  const [allAddresses, setAllAddresses] = useState<Address[]>([]);
  const [currentPage, setCurrentPage] = useState(1);
  const [isAddressesLoading, setIsAddressesLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isValidatingAddress, setIsValidatingAddress] = useState(false);
  const [selectionError, setSelectionError] = useState<string | null>(null);
  const selectionVersion = useRef(0);

  useEffect(() => {
    selectionVersion.current += 1;
    setIsValidatingAddress(false);
    return () => { selectionVersion.current += 1; };
  }, [zone_id, isOpen, tempSelectedId]);

  useEffect(() => {
    setAllAddresses([]);
    setCurrentPage(1);
    setTempSelectedId("");
  }, [zone_id]);

  // Create SWR keys that include resetTrigger to force refetch on reset
  const initialDataKey = useMemo(
    () => ["/cart-addresses/initial", zone_id] as const,
    [zone_id]
  );

  // Fetch initial data to get total count
  const { data: initialData, isLoading: initialLoading } = useSWR(
    initialDataKey,
    async () => {
      const location = getCookie("userLocation") as UserLocation | undefined;
      const { lat = "", lng = "" } = location || {};
      const response = await getAddresses({
        page: 1,
        per_page: 1,
        latitude: lat,
        longitude: lng,
        zone_id,
      });
      if (!response.success) {
        throw new Error(response.message || "Failed to fetch addresses");
      }
      return {
        addresses: response.data.data || [],
        total: response.data.total || 0,
      };
    }
  );

  // Derive total from initialData
  const total = initialData?.total || 0;
  const initialFetchDone = !!initialData;

  const fetchAddressesData = useCallback(
    async (page: number, perPage: number = 10) => {
      const location = getCookie("userLocation") as UserLocation | undefined;
      const { lat = "", lng = "" } = location || {};
      const response = await getAddresses({
        page,
        per_page: perPage,
        latitude: lat,
        longitude: lng,
        zone_id,
      });
      if (!response.success) {
        throw new Error(response.message || "Failed to fetch addresses");
      }

      const data = response.data;
      let addressesArray: Address[] = [];

      if (data && typeof data === "object" && "data" in data) {
        const nestedData = (data as any).data;
        if (Array.isArray(nestedData)) {
          addressesArray = nestedData;
        } else if (nestedData && typeof nestedData === "object") {
          addressesArray = Object.values(nestedData);
        }
      } else if (Array.isArray(data)) {
        addressesArray = data;
      }

      return addressesArray;
    },
    [zone_id]
  );

  // Applying an address can remove items server-side. Let the customer choose
  // explicitly instead of applying the first/default address on mount.

  // Reset function
  const handleReset = useCallback(async () => {
    // Reset local state and trigger refetch
    setAllAddresses([]);
    setCurrentPage(1);
    setTempSelectedId("");

    // Clear SWR cache for address-related keys
    await mutate(
      (key) =>
        Array.isArray(key) &&
        typeof key[0] === "string" &&
        key[0].includes("/cart-addresses"),
      undefined,
      { revalidate: true }
    );
  }, []);

  // Handle modal opening
  const handleSelectAddressClick = useCallback(async () => {
    setSelectionError(null);
    setTempSelectedId(selectedAddress?.id?.toString() || "");
    onOpen();

    if (allAddresses.length === 0 && total > 0) {
      setIsAddressesLoading(true);
      try {
        const addresses = await fetchAddressesData(1);
        setAllAddresses(addresses);
        setCurrentPage(1);
      } catch (error) {
        console.error("Error fetching initial addresses:", error);
      } finally {
        setIsAddressesLoading(false);
      }
    }
  }, [allAddresses.length, total, selectedAddress, onOpen, fetchAddressesData]);

  const handleLoadMore = useCallback(async () => {
    if (isLoadingMore || allAddresses.length >= total) return;

    setIsLoadingMore(true);
    try {
      const nextPage = currentPage + 1;
      const addresses = await fetchAddressesData(nextPage);
      setAllAddresses((prev) => [...prev, ...addresses]);
      setCurrentPage(nextPage);
    } catch (error) {
      console.error("Error loading more addresses:", error);
    } finally {
      setIsLoadingMore(false);
    }
  }, [
    isLoadingMore,
    allAddresses.length,
    total,
    currentPage,
    fetchAddressesData,
  ]);

  const handleModalSelection = useCallback((addressId: string) => {
    setSelectionError(null);
    setTempSelectedId(addressId);
  }, []);

  const handleConfirmSelection = useCallback(async () => {
    if (isValidatingAddress) return;
    if (tempSelectedId) {
      const selectedAddr = allAddresses.find(
        (addr) => addr.id.toString() === tempSelectedId
      );
      if (selectedAddr) {
        const version = ++selectionVersion.current;
        setIsValidatingAddress(true);
        setSelectionError(null);
        try {
          const confirmed = await confirmCartLocation(`address:${selectedAddr.id}`, {
            latitude: selectedAddr.latitude,
            longitude: selectedAddr.longitude,
          });
          if (version !== selectionVersion.current || !confirmed) return;
          // Pass the candidate's coordinates, not the previous Redux selection.
          const result = await updateCartData(false, false, {
            id: selectedAddr.id,
            latitude: selectedAddr.latitude,
            longitude: selectedAddr.longitude,
          });
          if (result?.success || isEmptyCartMessage(result?.message)) {
            dispatch(setSelectedAddress(selectedAddr));
            onClose();
          } else {
            setSelectionError(t("address.selectionFailed"));
          }
        } catch {
          setSelectionError(t("address.selectionFailed"));
        } finally {
          setIsValidatingAddress(false);
        }
      }
    }
  }, [tempSelectedId, allAddresses, dispatch, onClose, isValidatingAddress, t]);

  const getAddressTypeColor = (type: string) => {
    switch (type.toLowerCase()) {
      case "home":
        return "success" as const;
      case "work":
        return "primary" as const;
      case "other":
        return "warning" as const;
      default:
        return "default" as const;
    }
  };

  const getAddressTypeIcon = (type: string) => {
    switch (type.toLowerCase()) {
      case "home":
        return "🏠";
      case "work":
        return "🏢";
      case "other":
        return "📍";
      default:
        return "📍";
    }
  };

  const isLoading = initialLoading || isAddressesLoading;

  return (
    <>
      {/* Main Address Display Card */}
      <Card className="w-full" shadow="sm">
        <CardHeader className="flex justify-between flex-col sm:flex-row items-start pb-2 gap-2 w-full">
          <div className="flex items-center gap-3">
            <Button isIconOnly variant="flat" color="primary">
              <MapPin className="w-5 h-5 text-primary-600" />
            </Button>
            <div>
              <h3 className="text-sm font-semibold text-foreground">
                {t("address.deliveryAddress")}
              </h3>
              <p className="text-xxs md:text-xs text-default-500">
                {t("address.chooseLocation")}
              </p>
            </div>
          </div>
          <div className="flex gap-2 sm:justify-end justify-between items-center w-full sm:w-fit">
            {/* Reset Button */}
            <button
              id="reset-cart-addresses"
              className="text-xs hidden"
              onClick={handleReset}
              disabled={isLoading}
            >
              {t("reset")}
            </button>
            <Button
              size="sm"
              color="primary"
              variant="flat"
              startContent={<Plus className="w-4 h-4" />}
              className="text-xs"
              onPress={onAddAddressModalOpen}
            >
              {t("address.addNew")}
            </Button>
            <div className="space-y-3">
              <Button
                variant="bordered"
                color="primary"
                className="text-xs"
                size="sm"
                fullWidth
                onPress={handleSelectAddressClick}
                startContent={<MapPin className="w-4 h-4" />}
                endContent={<ChevronDown className="w-4 h-4" />}
                isDisabled={!initialFetchDone || total === 0}
                isLoading={isLoading}
              >
                {!initialFetchDone
                  ? t("address.loading")
                  : total === 0
                    ? t("address.noAddresses")
                    : t("address.selectAddress")}
              </Button>
            </div>
          </div>
        </CardHeader>

        <CardBody className="pt-0">
          {deliveryLocationNeedsSelection && (
            <p role="status" className="mb-3 rounded-lg bg-primary/10 p-3 text-xs leading-relaxed text-foreground">
              {t("address.deliveryNeedsSelection")}
            </p>
          )}
          {initialFetchDone && total === 0 && (
            <div className="w-full text-center rounded-lg">
              <div className="max-w-md mx-auto">
                <div className="w-10 h-10 mx-auto mb-2 bg-gray-200 rounded-full flex items-center justify-center">
                  <Plus className="w-5 h-5 text-gray-400" />
                </div>
                <h3 className="text-sm font-medium mb-2">
                  {t("pages.addresses.noAddresses")}
                </h3>
              </div>
            </div>
          )}
          {selectedAddress ? (
            // Show selected address with option to change
            <Button
              variant="bordered"
              className="w-full h-auto p-4 justify-start hover:bg-default-50 transition-colors"
              onPress={handleSelectAddressClick}
              endContent={<ChevronDown className="w-4 h-4 text-default-400" />}
            >
              <div className="flex items-start gap-3 w-full">
                <div className="text-lg">
                  {getAddressTypeIcon(selectedAddress.address_type)}
                </div>
                <div className="flex flex-col items-start gap-1 flex-1">
                  <div className="flex items-center gap-2">
                    <Chip
                      size="sm"
                      color={getAddressTypeColor(selectedAddress.address_type)}
                      variant="flat"
                      className="text-xs capitalize"
                    >
                      {selectedAddress.address_type}
                    </Chip>
                  </div>
                  <p className="text-xs sm:text-sm text-left text-foreground line-clamp-1 max-w-[50vw] text-ellipsis">
                    {selectedAddress.address_line1}
                    {selectedAddress.address_line2 &&
                      `, ${selectedAddress.address_line2}`}
                  </p>
                  <p className="text-xs text-default-500 text-left">
                    {selectedAddress.city}, {selectedAddress.state}{" "}
                    {selectedAddress.zipcode}
                  </p>
                </div>
              </div>
            </Button>
          ) : (
            // Show placeholder when no address is selected and we have addresses available
            initialFetchDone &&
            total > 0 && (
              <div className="p-4 border-2 border-dashed border-default-200 rounded-lg text-center">
                <p className="text-sm text-default-500 mb-2">
                  {t("address.noSelected")}
                </p>

                <Button
                  variant="flat"
                  color="primary"
                  size="sm"
                  className="text-xs"
                  onPress={handleSelectAddressClick}
                  startContent={<MapPin className="w-4 h-4" />}
                >
                  {t("address.chooseAddress")}
                </Button>
              </div>
            )
          )}
        </CardBody>
      </Card>

      {/* Address Selection Modal */}
      <AddressSelectionModal
        isOpen={isOpen}
        onOpenChange={onOpenChange}
        onAddNew={onAddAddressModalOpen}
        addresses={allAddresses}
        selectedAddressId={deliveryLocationNeedsSelection ? null : selectedAddress?.id?.toString() || null}
        tempSelectedId={tempSelectedId}
        handleModalSelection={handleModalSelection}
        handleConfirmSelection={handleConfirmSelection}
        getAddressTypeIcon={getAddressTypeIcon}
        getAddressTypeColor={getAddressTypeColor}
        isLoading={isAddressesLoading}
        isConfirming={isValidatingAddress}
        error={selectionError}
        totalAddresses={total}
        onLoadMore={handleLoadMore}
        isLoadingMore={isLoadingMore}
      />
    </>
  );
};

export default AddressSection;
