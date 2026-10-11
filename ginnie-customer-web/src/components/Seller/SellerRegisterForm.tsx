import { useState, useRef, useEffect, useMemo } from "react";
import { Input, Button, Card, CardBody, addToast } from "@heroui/react";
import {
  Upload,
  CheckCircle2,
  MapPin,
  FileCheck,
  User,
  Eye,
  EyeOff,
  X,
  Crosshair,
  AlertCircle,
  Sparkles,
} from "lucide-react";
import { sellerRegister } from "@/routes/api";
import { useTranslation } from "react-i18next";
import LocationAutoComplete from "@/components/Location/LocationAutoComplete";
import type { LocationAutoCompleteRef } from "@/components/Location/types/LocationAutoComplete.types";

interface FormData {
  sellerName: string;
  mobile: string;
  email: string;
  password: string;
  confirmPassword: string;
  address: string;
  city: string;
  landmark: string;
  state: string;
  zipcode: string;
  country: string;
  countryCode: string;
  latitude: string;
  longitude: string;
}

interface FileData {
  businessLicense: File | null;
  articlesOfIncorporation: File | null;
  nationalId: File | null;
  authorizedSignature: File | null;
}

// Allowed image MIME types
const validImageTypes = ["image/jpeg", "image/png", "image/jpg", "image/webp"];

/** The four uploads, in render order — also drives the "n of 4" progress count. */
const DOCUMENT_FIELDS: { key: keyof FileData; label: string }[] = [
  { key: "businessLicense", label: "pages.sellerRegister.docs.businessLicense" },
  {
    key: "articlesOfIncorporation",
    label: "pages.sellerRegister.docs.articlesOfIncorporation",
  },
  { key: "nationalId", label: "pages.sellerRegister.docs.nationalId" },
  {
    key: "authorizedSignature",
    label: "pages.sellerRegister.docs.authorizedSignature",
  },
];

/**
 * Shared HeroUI slot overrides so every field on the page has the same height,
 * radius and focus treatment. `variant="flat"` + outside labels matches the
 * login/register modals.
 *
 * `!` prefixes are load-bearing on the invalid state. When `isInvalid` is set,
 * HeroUI switches the field to `color="danger"`, and its `flat`+`danger`
 * compound variant paints `bg-danger-100` on the wrapper and `!text-danger` on
 * the label/placeholder. Those land at the same specificity as a plain utility,
 * so without `!` the theme wins on source order and every errored field renders
 * as a heavy pink block. Overriding to a light tint plus a danger border keeps
 * the error obvious while matching the density of the valid fields.
 */
const inputClassNames = {
  label: "text-xs font-medium",
  inputWrapper: [
    "bg-default-100/60 border border-transparent shadow-none transition-colors",
    "data-[hover=true]:bg-default-100",
    "group-data-[focus=true]:border-primary group-data-[focus=true]:bg-background",
    // Error: outline instead of the theme's solid danger fill.
    "group-data-[invalid=true]:!bg-danger-50/40",
    "group-data-[invalid=true]:!border-danger/50",
  ].join(" "),
  input: "text-sm placeholder:!text-foreground/35",
  errorMessage: "text-xs",
};

/** Numbered section heading: step chip, icon, title and optional trailing slot. */
function SectionHeader({
  step,
  icon,
  title,
  hint,
  action,
}: {
  step: number;
  icon: React.ReactNode;
  title: string;
  hint?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex items-center gap-3 mb-5">
      <div className="relative shrink-0">
        <div className="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
          {icon}
        </div>
        <span className="absolute -top-1.5 -left-1.5 w-5 h-5 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center shadow-sm">
          {step}
        </span>
      </div>
      <div className="min-w-0 flex-1">
        <h2 className="font-semibold text-base leading-tight">{title}</h2>
        {hint && <p className="text-xs text-foreground/50 mt-0.5">{hint}</p>}
      </div>
      {action}
    </div>
  );
}

export default function SellerRegisterForm() {
  const locationAutoCompleteRef = useRef<LocationAutoCompleteRef>(null);
  const { t } = useTranslation();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<{ [key: string]: string }>({});
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [dragTarget, setDragTarget] = useState<keyof FileData | null>(null);
  const [files, setFiles] = useState<FileData>({
    businessLicense: null,
    articlesOfIncorporation: null,
    nationalId: null,
    authorizedSignature: null,
  });
  const [formData, setFormData] = useState<FormData>({
    sellerName: "",
    mobile: "",
    email: "",
    password: "",
    confirmPassword: "",
    address: "",
    city: "",
    landmark: "",
    state: "",
    zipcode: "",
    country: "",
    countryCode: "",
    latitude: "",
    longitude: "",
  });

  /**
   * Thumbnails for the upload tiles. Object URLs are recreated whenever the
   * selected files change and revoked on the way out, otherwise each re-pick
   * would leak the previous blob for the lifetime of the page.
   */
  const previews = useMemo(
    () =>
      Object.fromEntries(
        DOCUMENT_FIELDS.map(({ key }) => [
          key,
          files[key] ? URL.createObjectURL(files[key]) : null,
        ]),
      ) as Record<keyof FileData, string | null>,
    [files],
  );

  useEffect(
    () => () => {
      Object.values(previews).forEach((url) => url && URL.revokeObjectURL(url));
    },
    [previews],
  );

  const uploadedCount = DOCUMENT_FIELDS.filter(({ key }) => files[key]).length;

  const resetState = () => {
    setFormData({
      sellerName: "",
      mobile: "",
      email: "",
      password: "",
      confirmPassword: "",
      address: "",
      city: "",
      landmark: "",
      state: "",
      zipcode: "",
      country: "",
      countryCode: "",
      latitude: "",
      longitude: "",
    });
    setFiles({
      businessLicense: null,
      articlesOfIncorporation: null,
      nationalId: null,
      authorizedSignature: null,
    });
    setErrors({});
    if (locationAutoCompleteRef.current) {
      locationAutoCompleteRef.current.setInputValue("");
    }
  };

  const handleLocationSelect = async (locationData: {
    placeName: string;
    latLng: { lat: number; lng: number };
    placeDescription: string;
  }) => {
    try {
      const geocoder = new window.google.maps.Geocoder();
      const result = await geocoder.geocode({ location: locationData.latLng });

      if (result.results && result.results.length > 0) {
        const place = result.results[0];
        let city = "";
        let state = "";
        let country = "";
        let zipcode = "";
        let countryCode = "";

        for (const component of place.address_components) {
          const componentType = component.types[0];

          switch (componentType) {
            case "locality":
              city = component.long_name;
              break;
            case "administrative_area_level_1":
              state = component.long_name;
              break;
            case "country":
              country = component.long_name;
              countryCode = component.short_name;
              break;
            case "postal_code":
              zipcode = component.long_name;
              break;
          }
        }

        const addressLine1 = place.formatted_address;

        setFormData((prev) => ({
          ...prev,
          address: addressLine1,
          city,
          state,
          zipcode,
          country,
          countryCode: countryCode,
          latitude: locationData.latLng.lat.toString(),
          longitude: locationData.latLng.lng.toString(),
        }));

        if (locationAutoCompleteRef.current) {
          locationAutoCompleteRef.current.setInputValue(
            place.formatted_address
          );
        }
      }
    } catch (error) {
      console.error("Geocoding error:", error);
    }
  };

  const handleInputChange = (name: keyof FormData, value: string) => {
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: "" }));
  };

  /** Shared by the file input and the drag-and-drop target. */
  const acceptFile = (name: keyof FileData, file: File | undefined) => {
    if (!file) return;

    // Validate by MIME type
    if (!validImageTypes.includes(file.type)) {
      addToast({
        title: t("pages.sellerRegister.toast.invalidFileTitle"),
        description: t("pages.sellerRegister.toast.invalidFileDescription"),
        color: "danger",
      });
      setFiles((prev) => ({ ...prev, [name]: null }));
      setErrors((prev) => ({
        ...prev,
        [name]: t("pages.sellerRegister.error.invalidFile"),
      }));
      return;
    }

    // Valid file
    setFiles((prev) => ({ ...prev, [name]: file }));
    setErrors((prev) => ({ ...prev, [name]: "" }));
  };

  const handleFileChange = (
    name: keyof FileData,
    event: React.ChangeEvent<HTMLInputElement>,
  ) => {
    acceptFile(name, event.target.files?.[0]);
    // Clear the input so re-picking the same file still fires onChange.
    event.target.value = "";
  };

  const handleFileDrop = (
    name: keyof FileData,
    event: React.DragEvent<HTMLDivElement>,
  ) => {
    event.preventDefault();
    setDragTarget(null);
    acceptFile(name, event.dataTransfer.files?.[0]);
  };

  const removeFile = (name: keyof FileData) => {
    setFiles((prev) => ({ ...prev, [name]: null }));
    setErrors((prev) => ({ ...prev, [name]: "" }));
  };

  const validateForm = () => {
    const newErrors: { [key: string]: string } = {};

    // Text field validation
    if (!formData.sellerName)
      newErrors.sellerName = t("pages.sellerRegister.error.required");
    if (!formData.mobile)
      newErrors.mobile = t("pages.sellerRegister.error.required");
    if (!formData.email)
      newErrors.email = t("pages.sellerRegister.error.required");
    if (!formData.password)
      newErrors.password = t("pages.sellerRegister.error.required");
    if (formData.password !== formData.confirmPassword)
      newErrors.confirmPassword = t(
        "pages.sellerRegister.error.passwordMismatch"
      );
    if (!formData.address)
      newErrors.address = t("pages.sellerRegister.error.required");
    if (!formData.city)
      newErrors.city = t("pages.sellerRegister.error.required");
    if (!formData.state)
      newErrors.state = t("pages.sellerRegister.error.required");
    if (!formData.zipcode)
      newErrors.zipcode = t("pages.sellerRegister.error.required");
    if (!formData.landmark)
      newErrors.landmark = t("pages.sellerRegister.error.required");
    if (!formData.country)
      newErrors.country = t("pages.sellerRegister.error.required");
    if (!formData.latitude)
      newErrors.latitude = t("pages.sellerRegister.error.required");
    if (!formData.longitude)
      newErrors.longitude = t("pages.sellerRegister.error.required");

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      addToast({
        title: t("pages.sellerRegister.toast.validationErrorTitle"),
        description: t("pages.sellerRegister.toast.validationErrorDesc"),
        color: "danger",
      });
      return false;
    }

    // File presence + type validation (extra safety)
    const missingFiles: string[] = [];
    const invalidTypes: string[] = [];

    const checkFile = (
      file: File | null,
      label: string,
      key: keyof FileData
    ) => {
      if (!file) {
        missingFiles.push(label);
        newErrors[key as string] = t("pages.sellerRegister.error.required");
      } else if (!validImageTypes.includes(file.type)) {
        invalidTypes.push(label);
        newErrors[key as string] = t("pages.sellerRegister.error.invalidFile");
      }
    };

    checkFile(files.businessLicense, "Business License", "businessLicense");
    checkFile(
      files.articlesOfIncorporation,
      "Articles of Incorporation",
      "articlesOfIncorporation"
    );
    checkFile(files.nationalId, "National ID Card", "nationalId");
    checkFile(
      files.authorizedSignature,
      "Authorized Signature",
      "authorizedSignature"
    );

    if (missingFiles.length > 0) {
      addToast({
        title: t("pages.sellerRegister.toast.missingDocumentsTitle"),
        description: t("pages.sellerRegister.toast.missingDocumentsDesc", {
          files: missingFiles.join(", "),
        }),
        color: "warning",
      });
    }

    if (invalidTypes.length > 0) {
      addToast({
        title: t("pages.sellerRegister.toast.invalidFileTypeTitle"),
        description: t("pages.sellerRegister.toast.invalidFileTypeDesc", {
          types: invalidTypes.join(", "),
        }),
        color: "danger",
      });
    }

    setErrors(newErrors);
    return (
      Object.keys(newErrors).length === 0 &&
      missingFiles.length === 0 &&
      invalidTypes.length === 0
    );
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    setIsSubmitting(true);

    try {
      // Use FormData for multipart/form-data file upload
      const submitData = new FormData();

      // Append text fields
      submitData.append("name", formData.sellerName);
      submitData.append("email", formData.email);
      submitData.append("mobile", formData.mobile);
      submitData.append("password", formData.password);
      submitData.append("address", formData.address);
      submitData.append("city", formData.city);
      submitData.append("state", formData.state);
      submitData.append("landmark", formData.landmark);
      submitData.append("zipcode", formData.zipcode);
      submitData.append("country", formData.country);
      submitData.append("latitude", formData.latitude);
      submitData.append("longitude", formData.longitude);

      // Append files directly as binary
      if (files.businessLicense) {
        submitData.append("business_license", files.businessLicense);
      }
      if (files.articlesOfIncorporation) {
        submitData.append(
          "articles_of_incorporation",
          files.articlesOfIncorporation
        );
      }
      if (files.nationalId) {
        submitData.append("national_identity_card", files.nationalId);
      }
      if (files.authorizedSignature) {
        submitData.append("authorized_signature", files.authorizedSignature);
      }

      const response = await sellerRegister(submitData);

      // Check if response has errors (validation errors) or if success is false
      const hasErrors =
        (response.errors &&
          (typeof response.errors === "object" ||
            Array.isArray(response.errors))) ||
        response.success === false;

      if (response.success === true) {
        resetState();
        addToast({
          title: t("pages.sellerRegister.toast.successTitle"),
          description: t("pages.sellerRegister.toast.successDesc"),
          color: "success",
        });

        setTimeout(() => {
          window
            .open(`${process.env.NEXT_PUBLIC_ADMIN_PANEL_URL}/seller`, "_blank")
            ?.focus();
        }, 2000);
      } else if (hasErrors || !response.success) {
        // Handle API validation errors
        const errorMessages: string[] = [];
        const fieldErrors: { [key: string]: string } = {};

        // Check if response has validation errors object (not array)
        if (
          response.errors &&
          typeof response.errors === "object" &&
          !Array.isArray(response.errors)
        ) {
          const errors = response.errors as {
            [key: string]: string[] | string;
          };

          // Process each field error
          Object.keys(errors).forEach((field) => {
            const fieldError = errors[field];
            const errorMessagesArray = Array.isArray(fieldError)
              ? fieldError
              : [fieldError];

            // Add first error message to toast
            if (errorMessagesArray.length > 0) {
              errorMessages.push(errorMessagesArray[0]);
            }

            // Map API field names to form field names
            let formFieldName = field;
            if (field === "email") formFieldName = "email";
            else if (field === "mobile") formFieldName = "mobile";
            else if (field === "name") formFieldName = "sellerName";
            else if (field === "password") formFieldName = "password";

            // Set field error in form state
            if (errorMessagesArray.length > 0) {
              fieldErrors[formFieldName] = errorMessagesArray[0];
            }
          });
        } else if (Array.isArray(response.errors)) {
          // Handle case where errors is an array
          response.errors.forEach((error) => {
            if (typeof error === "string") {
              errorMessages.push(error);
            }
          });
        }

        // Update form errors state
        if (Object.keys(fieldErrors).length > 0) {
          setErrors((prev) => ({ ...prev, ...fieldErrors }));
        }

        // Show toast with all error messages
        const errorMessage =
          errorMessages.length > 0
            ? errorMessages.join(". ")
            : response.message ||
              t("pages.sellerRegister.toast.genericErrorDesc");

        addToast({
          title: t("pages.sellerRegister.toast.errorTitle"),
          description: errorMessage,
          color: "danger",
        });
      }
    } catch (error: any) {
      console.error("Registration error:", error);

      // Handle axios errors with validation responses
      const errorMessages: string[] = [];
      const fieldErrors: { [key: string]: string } = {};

      if (error?.response?.data) {
        const errorData = error.response.data;

        // Check if error response has validation errors (not array)
        if (
          errorData.errors &&
          typeof errorData.errors === "object" &&
          !Array.isArray(errorData.errors)
        ) {
          const errors = errorData.errors as {
            [key: string]: string[] | string;
          };

          // Process each field error
          Object.keys(errors).forEach((field) => {
            const fieldError = errors[field];
            const errorMessagesArray = Array.isArray(fieldError)
              ? fieldError
              : [fieldError];

            // Add first error message to toast
            if (errorMessagesArray.length > 0) {
              errorMessages.push(errorMessagesArray[0]);
            }

            // Map API field names to form field names
            let formFieldName = field;
            if (field === "email") formFieldName = "email";
            else if (field === "mobile") formFieldName = "mobile";
            else if (field === "name") formFieldName = "sellerName";
            else if (field === "password") formFieldName = "password";

            // Set field error in form state
            if (errorMessagesArray.length > 0) {
              fieldErrors[formFieldName] = errorMessagesArray[0];
            }
          });
        } else if (Array.isArray(errorData.errors)) {
          // Handle case where errors is an array
          errorData.errors.forEach((error: any) => {
            if (typeof error === "string") {
              errorMessages.push(error);
            }
          });
        }

        // Update form errors state
        if (Object.keys(fieldErrors).length > 0) {
          setErrors((prev) => ({ ...prev, ...fieldErrors }));
        }

        // Show toast with error messages
        const errorMessage =
          errorMessages.length > 0
            ? errorMessages.join(". ")
            : errorData.message ||
              t("pages.sellerRegister.toast.unexpectedError");

        addToast({
          title: t("pages.sellerRegister.toast.errorTitle"),
          description: errorMessage,
          color: "danger",
        });
      } else {
        // Generic error for unexpected errors
        addToast({
          title: t("pages.sellerRegister.toast.errorTitle"),
          description: t("pages.sellerRegister.toast.unexpectedError"),
          color: "danger",
        });
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  /**
   * Drop-or-click upload tile. Shows a thumbnail once a valid image is picked,
   * since every accepted type here is an image and the preview is the fastest
   * way for a seller to confirm they attached the right document.
   */
  const FileUploadField = ({
    label,
    name,
  }: {
    label: string;
    name: keyof FileData;
  }) => {
    const file = files[name];
    const error = errors[name];
    const preview = previews[name];
    const isDragging = dragTarget === name;

    return (
      <div className="flex flex-col gap-1.5">
        <label htmlFor={name} className="text-xs font-medium text-foreground/70">
          {t(label)} <span className="text-danger">*</span>
        </label>

        <div
          onDragOver={(e) => {
            e.preventDefault();
            setDragTarget(name);
          }}
          onDragLeave={() => setDragTarget(null)}
          onDrop={(e) => handleFileDrop(name, e)}
          className={`relative rounded-xl border border-dashed transition-colors ${
            error
              ? "border-danger bg-danger-50/50"
              : file
                ? "border-success/50 bg-success-50/40"
                : isDragging
                  ? "border-primary bg-primary-50/60 dark:bg-primary-500/15"
                  : "border-default-300 bg-default-100/40 hover:border-primary hover:bg-primary-50/30 dark:hover:bg-primary-500/10"
          }`}
        >
          <input
            type="file"
            id={name}
            className="hidden"
            accept="image/jpeg,image/png,image/jpg,image/webp,.jpeg,.png,.jpg,.webp"
            onChange={(e) => handleFileChange(name, e)}
          />

          {file && !error ? (
            <div className="flex items-center gap-3 p-2.5">
              {preview && (
                // Blob URL of a just-picked local file — next/image can't
                // optimise it, so a plain img is correct here.
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={preview}
                  alt=""
                  className="w-10 h-10 rounded-lg object-cover shrink-0 border border-divider"
                />
              )}
              <div className="min-w-0 flex-1">
                <p className="text-xs font-medium truncate">{file.name}</p>
                <p className="text-[11px] text-foreground/50 flex items-center gap-1">
                  <CheckCircle2 className="w-3 h-3 text-success" />
                  {t("pages.sellerRegister.upload.uploaded")}
                </p>
              </div>
              <div className="flex items-center gap-1 shrink-0">
                <label
                  htmlFor={name}
                  className="text-[11px] font-medium text-primary hover:underline cursor-pointer px-1"
                >
                  {t("pages.sellerRegister.upload.replace")}
                </label>
                <button
                  type="button"
                  aria-label={t("pages.sellerRegister.upload.remove")}
                  onClick={() => removeFile(name)}
                  className="p-1 rounded-md text-foreground/40 hover:text-danger hover:bg-danger-50 transition-colors"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          ) : (
            <label
              htmlFor={name}
              className="flex flex-col items-center justify-center gap-1 py-4 px-3 cursor-pointer text-center"
            >
              <Upload
                className={`w-4 h-4 ${error ? "text-danger" : "text-foreground/40"}`}
              />
              <span
                className={`text-xs font-medium ${error ? "text-danger" : "text-foreground/70"}`}
              >
                {t("pages.sellerRegister.upload.cta")}
              </span>
              <span className="text-[10px] text-foreground/40">
                {t("pages.sellerRegister.upload.formats")}
              </span>
            </label>
          )}
        </div>

        {error && (
          <p className="text-xs text-danger flex items-center gap-1">
            <AlertCircle className="w-3 h-3 shrink-0" />
            {error}
          </p>
        )}
      </div>
    );
  };

  return (
    <div className="w-full px-4 pb-12" id="seller-register">
      <Card className="border border-divider shadow-sm w-full max-w-7xl mx-auto overflow-visible">
        <CardBody className="p-0">
          {/* Header strip — sets context before the fields start. */}
          <div className="relative overflow-hidden px-6 md:px-8 pt-7 pb-6 border-b border-divider bg-linear-to-br from-primary-50/60 to-transparent dark:from-primary-500/12 dark:via-primary-500/5 dark:to-transparent">
            {/* Soft primary bloom — keeps the strip from reading as a flat grey slab in dark mode. */}
            <div
              aria-hidden
              className="pointer-events-none absolute -top-24 -left-16 hidden dark:block w-80 h-80 rounded-full bg-primary/15 blur-3xl"
            />
            <div className="relative">
              <div className="inline-flex items-center gap-1.5 bg-primary/10 dark:bg-primary/20 text-primary dark:text-primary-400 px-2.5 py-1 rounded-full text-[11px] font-semibold border border-primary/20 dark:border-primary/30 mb-2.5">
                <Sparkles className="w-3 h-3" />
                {t("pages.sellerRegister.form.badge")}
              </div>
              <h2 className="text-xl md:text-2xl font-bold leading-tight text-foreground">
                {t("pages.sellerRegister.form.heading")}
              </h2>
              <p className="text-sm text-foreground/60 dark:text-foreground/70 mt-1 max-w-2xl">
                {t("pages.sellerRegister.form.subheading")}
              </p>
            </div>
          </div>

          <div className="p-6 md:p-8">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-10 items-start">
              {/* Left column — identity + documents */}
              <div className="flex flex-col gap-8">
                <section>
                  <SectionHeader
                    step={1}
                    icon={<User className="w-4 h-4" />}
                    title={t("pages.sellerRegister.personalInfo")}
                    hint={t("pages.sellerRegister.hint.personalInfo")}
                  />

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-5">
                    <Input
                      label={t("pages.sellerRegister.form.sellerName")}
                      placeholder={t(
                        "pages.sellerRegister.placeholder.sellerName",
                      )}
                      value={formData.sellerName}
                      onValueChange={(v) => handleInputChange("sellerName", v)}
                      isInvalid={!!errors.sellerName}
                      errorMessage={errors.sellerName}
                      labelPlacement="outside"
                      variant="flat"
                      radius="md"
                      autoComplete="name"
                      isRequired
                      classNames={inputClassNames}
                    />
                    <Input
                      label={t("pages.sellerRegister.form.mobile")}
                      placeholder={t("pages.sellerRegister.placeholder.mobile")}
                      value={formData.mobile}
                      onValueChange={(v) => {
                        if (/^\d*$/.test(v)) {
                          handleInputChange("mobile", v);
                        }
                      }}
                      isInvalid={!!errors.mobile}
                      errorMessage={errors.mobile}
                      labelPlacement="outside"
                      variant="flat"
                      radius="md"
                      type="tel"
                      inputMode="numeric"
                      autoComplete="tel"
                      isRequired
                      maxLength={10}
                      classNames={inputClassNames}
                    />
                    <div className="md:col-span-2">
                      <Input
                        label={t("pages.sellerRegister.form.email")}
                        placeholder={t("pages.sellerRegister.placeholder.email")}
                        value={formData.email}
                        onValueChange={(v) => handleInputChange("email", v)}
                        isInvalid={!!errors.email}
                        errorMessage={errors.email}
                        labelPlacement="outside"
                        variant="flat"
                        radius="md"
                        isRequired
                        type="email"
                        autoComplete="email"
                        classNames={inputClassNames}
                      />
                    </div>
                    <Input
                      label={t("pages.sellerRegister.form.password")}
                      placeholder={t(
                        "pages.sellerRegister.placeholder.password",
                      )}
                      type={showPassword ? "text" : "password"}
                      value={formData.password}
                      onValueChange={(v) => handleInputChange("password", v)}
                      labelPlacement="outside"
                      variant="flat"
                      radius="md"
                      autoComplete="new-password"
                      isInvalid={!!errors.password}
                      errorMessage={errors.password}
                      isRequired
                      classNames={inputClassNames}
                      endContent={
                        <button
                          type="button"
                          aria-label={t(
                            showPassword
                              ? "pages.sellerRegister.form.hidePassword"
                              : "pages.sellerRegister.form.showPassword",
                          )}
                          onClick={() => setShowPassword((s) => !s)}
                          className="text-foreground/40 hover:text-foreground/70 transition-colors focus:outline-none"
                        >
                          {showPassword ? (
                            <EyeOff className="w-4 h-4" />
                          ) : (
                            <Eye className="w-4 h-4" />
                          )}
                        </button>
                      }
                    />
                    <Input
                      label={t("pages.sellerRegister.form.confirmPassword")}
                      placeholder={t(
                        "pages.sellerRegister.placeholder.confirmPassword",
                      )}
                      type={showConfirmPassword ? "text" : "password"}
                      value={formData.confirmPassword}
                      onValueChange={(v) =>
                        handleInputChange("confirmPassword", v)
                      }
                      labelPlacement="outside"
                      variant="flat"
                      radius="md"
                      autoComplete="new-password"
                      isInvalid={!!errors.confirmPassword}
                      errorMessage={errors.confirmPassword}
                      isRequired
                      classNames={inputClassNames}
                      endContent={
                        <button
                          type="button"
                          aria-label={t(
                            showConfirmPassword
                              ? "pages.sellerRegister.form.hidePassword"
                              : "pages.sellerRegister.form.showPassword",
                          )}
                          onClick={() => setShowConfirmPassword((s) => !s)}
                          className="text-foreground/40 hover:text-foreground/70 transition-colors focus:outline-none"
                        >
                          {showConfirmPassword ? (
                            <EyeOff className="w-4 h-4" />
                          ) : (
                            <Eye className="w-4 h-4" />
                          )}
                        </button>
                      }
                    />
                  </div>
                </section>

                <section>
                  <SectionHeader
                    step={3}
                    icon={<FileCheck className="w-4 h-4" />}
                    title={t("pages.sellerRegister.requiredDocs")}
                    hint={t("pages.sellerRegister.hint.requiredDocs")}
                    action={
                      <span
                        className={`shrink-0 text-[11px] font-semibold px-2.5 py-1 rounded-full border ${
                          uploadedCount === DOCUMENT_FIELDS.length
                            ? "bg-success-50 text-success-600 border-success/30"
                            : "bg-default-100 text-foreground/60 border-divider"
                        }`}
                      >
                        {t("pages.sellerRegister.upload.progress", {
                          count: uploadedCount,
                          total: DOCUMENT_FIELDS.length,
                        })}
                      </span>
                    }
                  />

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {DOCUMENT_FIELDS.map(({ key, label }) => (
                      <FileUploadField key={key} name={key} label={label} />
                    ))}
                  </div>
                </section>
              </div>

              {/* Right column — address */}
              <section className="lg:sticky lg:top-24">
                <SectionHeader
                  step={2}
                  icon={<MapPin className="w-4 h-4" />}
                  title={t("pages.sellerRegister.businessAddress")}
                  hint={t("pages.sellerRegister.hint.businessAddress")}
                />

                <div className="rounded-xl border border-divider bg-default-50/50 p-4 mb-5">
                  <p className="text-xs font-medium text-foreground/70 mb-2 flex items-center gap-1.5">
                    <Crosshair className="w-3.5 h-3.5 text-primary" />
                    {t("pages.sellerRegister.form.searchLocation")}
                  </p>
                  <LocationAutoComplete
                    ref={locationAutoCompleteRef}
                    onLocationSelect={handleLocationSelect}
                    initialLocation={null}
                    variant="flat"
                    inputWrapperClassName="bg-default-100/60 border border-transparent shadow-none transition-colors data-[hover=true]:bg-default-100 group-data-[focus=true]:border-primary group-data-[focus=true]:bg-background"
                  />
                  <p className="text-[11px] text-foreground/45 mt-2">
                    {t("pages.sellerRegister.hint.searchLocation")}
                  </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-5">
                  <div className="md:col-span-2">
                    <Input
                      label={t("pages.sellerRegister.form.address")}
                      placeholder={t("pages.sellerRegister.placeholder.address")}
                      value={formData.address}
                      onValueChange={(v) => handleInputChange("address", v)}
                      labelPlacement="outside"
                      variant="flat"
                      radius="md"
                      autoComplete="street-address"
                      isInvalid={!!errors.address}
                      errorMessage={errors.address}
                      isRequired
                      classNames={inputClassNames}
                    />
                  </div>
                  <Input
                    label={t("pages.sellerRegister.form.landmark")}
                    placeholder={t("pages.sellerRegister.placeholder.landmark")}
                    value={formData.landmark}
                    onValueChange={(v) => handleInputChange("landmark", v)}
                    labelPlacement="outside"
                    variant="flat"
                    radius="md"
                    isInvalid={!!errors.landmark}
                    errorMessage={errors.landmark}
                    isRequired
                    classNames={inputClassNames}
                  />
                  <Input
                    label={t("pages.sellerRegister.form.city")}
                    placeholder={t("pages.sellerRegister.placeholder.city")}
                    value={formData.city}
                    onValueChange={(v) => handleInputChange("city", v)}
                    labelPlacement="outside"
                    variant="flat"
                    radius="md"
                    autoComplete="address-level2"
                    isInvalid={!!errors.city}
                    errorMessage={errors.city}
                    isRequired
                    classNames={inputClassNames}
                  />
                  <Input
                    label={t("pages.sellerRegister.form.state")}
                    placeholder={t("pages.sellerRegister.placeholder.state")}
                    value={formData.state}
                    onValueChange={(v) => handleInputChange("state", v)}
                    labelPlacement="outside"
                    variant="flat"
                    radius="md"
                    autoComplete="address-level1"
                    isInvalid={!!errors.state}
                    errorMessage={errors.state}
                    isRequired
                    classNames={inputClassNames}
                  />
                  <Input
                    label={t("pages.sellerRegister.form.zipcode")}
                    placeholder={t("pages.sellerRegister.placeholder.zipcode")}
                    value={formData.zipcode}
                    onValueChange={(v) => handleInputChange("zipcode", v)}
                    labelPlacement="outside"
                    variant="flat"
                    radius="md"
                    inputMode="numeric"
                    autoComplete="postal-code"
                    isInvalid={!!errors.zipcode}
                    errorMessage={errors.zipcode}
                    isRequired
                    classNames={inputClassNames}
                  />
                  <div className="md:col-span-2">
                    <Input
                      label={t("pages.sellerRegister.form.country")}
                      placeholder={t("pages.sellerRegister.placeholder.country")}
                      value={formData.country}
                      onValueChange={(v) => handleInputChange("country", v)}
                      labelPlacement="outside"
                      variant="flat"
                      radius="md"
                      autoComplete="country-name"
                      isInvalid={!!errors.country}
                      errorMessage={errors.country}
                      isRequired
                      classNames={inputClassNames}
                    />
                  </div>

                  {/* Coordinates are normally filled in by the search above, so
                      they're de-emphasised into their own labelled group. */}
                  <div className="md:col-span-2 rounded-xl border border-divider bg-default-50/50 p-4">
                    <p className="text-xs font-medium text-foreground/70 mb-3">
                      {t("pages.sellerRegister.form.coordinates")}
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-5">
                      <Input
                        label={t("pages.sellerRegister.form.latitude")}
                        placeholder={t(
                          "pages.sellerRegister.placeholder.latitude",
                        )}
                        value={formData.latitude}
                        labelPlacement="outside"
                        variant="flat"
                        radius="md"
                        inputMode="decimal"
                        onValueChange={(v) => {
                          // Allow empty string or valid number format
                          if (v === "" || /^-?\d*\.?\d*$/.test(v)) {
                            handleInputChange("latitude", v);
                          }
                        }}
                        isInvalid={!!errors.latitude}
                        errorMessage={errors.latitude}
                        isRequired
                        classNames={inputClassNames}
                      />
                      <Input
                        label={t("pages.sellerRegister.form.longitude")}
                        placeholder={t(
                          "pages.sellerRegister.placeholder.longitude",
                        )}
                        value={formData.longitude}
                        labelPlacement="outside"
                        variant="flat"
                        radius="md"
                        inputMode="decimal"
                        onValueChange={(v) => {
                          // Allow empty string or valid number format
                          if (v === "" || /^-?\d*\.?\d*$/.test(v)) {
                            handleInputChange("longitude", v);
                          }
                        }}
                        isInvalid={!!errors.longitude}
                        errorMessage={errors.longitude}
                        isRequired
                        classNames={inputClassNames}
                      />
                    </div>
                  </div>
                </div>
              </section>
            </div>
          </div>

          {/* Action bar — full width so it reads as the end of the form. */}
          <div className="px-6 md:px-8 py-5 border-t border-divider bg-default-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p className="text-xs text-foreground/50 flex items-center gap-1.5">
              <CheckCircle2 className="w-3.5 h-3.5 text-success shrink-0" />
              {t("pages.sellerRegister.form.footerNote")}
            </p>
            <div className="flex gap-3 sm:justify-end">
              <Button
                variant="bordered"
                radius="md"
                className="flex-1 sm:flex-none"
                isDisabled={isSubmitting}
                onPress={() => resetState()}
              >
                {t("pages.sellerRegister.button.reset")}
              </Button>
              <Button
                color="primary"
                radius="md"
                className="flex-1 sm:flex-none font-semibold px-8 text-white shadow-md hover:shadow-lg transition-shadow"
                onPress={handleSubmit}
                isLoading={isSubmitting}
              >
                {t("pages.sellerRegister.button.register")}
              </Button>
            </div>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
