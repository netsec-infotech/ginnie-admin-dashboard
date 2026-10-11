import {
  CheckCircle,
  Users,
  Package,
  Clock,
  Truck,
  DollarSign,
  BarChart3,
  Phone,
  Mail,
  Shield,
  Zap,
  ArrowRight,
  Award,
  Sparkles,
} from "lucide-react";
import { useSettings } from "@/contexts/SettingsContext";
import { Button } from "@heroui/react";
import { useTranslation } from "react-i18next";

export default function EnhancedSellerMarketing() {
  const { webSettings } = useSettings();
  const { t } = useTranslation();

  const siteName = webSettings?.siteName || "";

  // Hero copy is admin-driven; the i18n strings stay as fallbacks so the banner
  // still reads correctly before settings load or when a field is left blank.
  const heroBadge =
    webSettings?.sellerBannerIconText ||
    t("pages.enhancedSellerMarketing.hero.badge");
  const heroTitle =
    webSettings?.sellerBannerTitle ||
    t("pages.enhancedSellerMarketing.hero.titleMain");
  const heroTitleAccent =
    webSettings?.sellerBannerTitleHighlight ||
    t("pages.enhancedSellerMarketing.hero.titleAccent");
  const heroDescription =
    webSettings?.sellerBannerDescription ||
    t("pages.enhancedSellerMarketing.hero.description");
  const heroBackground =
    webSettings?.sellerBannerBackgroundImage || "/images/seller-banner.png";

  const marqueeItems = webSettings?.sellerBannerFeatures?.filter(Boolean)?.length
    ? webSettings.sellerBannerFeatures.filter(Boolean)
    : [
        t("pages.enhancedSellerMarketing.marquee.contentMarketing"),
        t("pages.enhancedSellerMarketing.marquee.socialMediaMarketing"),
        t("pages.enhancedSellerMarketing.marquee.seo"),
        t("pages.enhancedSellerMarketing.marquee.businessGrowth"),
      ];

  const benefits = [
    {
      icon: <Truck className="w-5 h-5" />,
      title: t(
        "pages.enhancedSellerMarketing.benefits.items.deliveryIntegrations.title"
      ),
      description: t(
        "pages.enhancedSellerMarketing.benefits.items.deliveryIntegrations.desc"
      ),
      metric: t(
        "pages.enhancedSellerMarketing.benefits.items.deliveryIntegrations.metric"
      ),
    },
    {
      icon: <DollarSign className="w-5 h-5" />,
      title: t(
        "pages.enhancedSellerMarketing.benefits.items.flexibleOnboarding.title"
      ),
      description: t(
        "pages.enhancedSellerMarketing.benefits.items.flexibleOnboarding.desc"
      ),
      metric: t(
        "pages.enhancedSellerMarketing.benefits.items.flexibleOnboarding.metric"
      ),
    },
    {
      icon: <Clock className="w-5 h-5" />,
      title: t(
        "pages.enhancedSellerMarketing.benefits.items.localFulfillment.title"
      ),
      description: t(
        "pages.enhancedSellerMarketing.benefits.items.localFulfillment.desc"
      ),
      metric: t(
        "pages.enhancedSellerMarketing.benefits.items.localFulfillment.metric"
      ),
    },
    {
      icon: <BarChart3 className="w-5 h-5" />,
      title: t(
        "pages.enhancedSellerMarketing.benefits.items.businessInsights.title"
      ),
      description: t(
        "pages.enhancedSellerMarketing.benefits.items.businessInsights.desc"
      ),
      metric: t(
        "pages.enhancedSellerMarketing.benefits.items.businessInsights.metric"
      ),
    },
    {
      icon: <Shield className="w-5 h-5" />,
      title: t(
        "pages.enhancedSellerMarketing.benefits.items.trustedPayments.title"
      ),
      description: t(
        "pages.enhancedSellerMarketing.benefits.items.trustedPayments.desc"
      ),
      metric: t(
        "pages.enhancedSellerMarketing.benefits.items.trustedPayments.metric"
      ),
    },
    {
      icon: <Users className="w-5 h-5" />,
      title: t(
        "pages.enhancedSellerMarketing.benefits.items.customerReach.title"
      ),
      description: t(
        "pages.enhancedSellerMarketing.benefits.items.customerReach.desc"
      ),
      metric: t(
        "pages.enhancedSellerMarketing.benefits.items.customerReach.metric"
      ),
    },
  ];

  const steps = [
    {
      step: "1",
      title: t("pages.enhancedSellerMarketing.how.steps.register.title"),
      desc: t("pages.enhancedSellerMarketing.how.steps.register.desc"),
      icon: <Users className="w-5 h-5" />,
    },
    {
      step: "2",
      title: t("pages.enhancedSellerMarketing.how.steps.list.title"),
      desc: t("pages.enhancedSellerMarketing.how.steps.list.desc"),
      icon: <Package className="w-5 h-5" />,
    },
    {
      step: "3",
      title: t("pages.enhancedSellerMarketing.how.steps.start.title"),
      desc: t("pages.enhancedSellerMarketing.how.steps.start.desc"),
      icon: <Truck className="w-5 h-5" />,
    },
  ];

  return (
    <div className="w-full">
      {/* Hero Section */}
      <div
        className="relative overflow-hidden rounded-md bg-cover bg-center bg-no-repeat"
        style={{ backgroundImage: `url('${heroBackground}')` }}
      >
        <div className="absolute top-0 right-0 w-96 h-96 bg-white opacity-5 rounded-full -mr-48 -mt-48" />
        <div className="absolute bottom-0 left-0 w-96 h-96 bg-black opacity-5 rounded-full -ml-48 -mb-48" />
        <div className="relative max-w-7xl mx-auto px-4 py-10 md:py-14">
          <div className="grid md:grid-cols-2 gap-10 items-center">
            <div className="space-y-5">
              <div className="inline-flex items-center gap-2 bg-yellow-400 px-3 py-1.5 text-white rounded-full text-xs font-bold">
                <Sparkles className="w-3.5 h-3.5" />
                {heroBadge}
              </div>

              <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold leading-tight text-white">
                {heroTitle}
                <span className="block text-yellow-300 mt-1">
                  {heroTitleAccent}
                </span>
              </h1>

              <p className="text-base text-primary-50 leading-relaxed">
                {heroDescription}
              </p>

              <div className="flex flex-wrap gap-3">
                <Button
                  variant="flat"
                  radius="sm"
                  size="md"
                  className="bg-white text-black font-semibold shadow-md hover:shadow-lg transition-all flex items-center gap-2"
                  endContent={<ArrowRight className="w-4 h-4" />}
                  onPress={() => {
                    const element = document.getElementById("seller-register");
                    const offset = -80;
                    if (element) {
                      const top =
                        element.getBoundingClientRect().top +
                        window.scrollY +
                        offset;
                      window.scrollTo({ top, behavior: "smooth" });
                    }
                  }}
                >
                  {t("pages.enhancedSellerMarketing.hero.registerNow")}
                </Button>
              </div>
            </div>
          </div>
        </div>
        <div className="bg-black/10 backdrop-blur-sm border-y border-white/20 py-2.5 overflow-hidden">
          <div className="flex gap-8 animate-marquee whitespace-nowrap">
            {[...Array(3)].map((_, i) => (
              <div key={i} className="flex gap-8 items-center text-white">
                {marqueeItems.map((item, idx) => (
                  <span
                    key={idx}
                    className="flex items-center gap-2 text-xs font-medium"
                  >
                    <Zap className="w-3.5 h-3.5 text-yellow-300" />
                    {item}
                  </span>
                ))}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Benefits Section */}
      <div className="max-w-7xl mx-auto px-4 py-12">
        <div className="text-center mb-8">
          <div className="inline-flex items-center gap-2 bg-primary/10 text-primary px-3 py-1.5 rounded-xl text-xs font-semibold mb-3 border border-primary/20">
            <Award className="w-3.5 h-3.5 stroke-2" />
            {t("pages.enhancedSellerMarketing.benefits.badge")}
          </div>
          <h2 className="text-2xl md:text-3xl font-bold mb-2">
            {t("pages.enhancedSellerMarketing.benefits.title")}
          </h2>
          <p className="text-sm max-w-2xl mx-auto text-foreground/50">
            {t("pages.enhancedSellerMarketing.benefits.description")}
          </p>
        </div>

        <div className="grid w-full sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {benefits.map((benefit, idx) => (
            <div
              key={idx}
              className="h-full group bg-content1 rounded-2xl p-5 border border-divider hover:border-primary-400 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 flex flex-col"
            >
              <div className="flex items-start justify-between mb-3">
                <div className="relative">
                  <div className="absolute inset-0 bg-primary-400/20 blur-xl rounded-full group-hover:bg-primary-400/30 transition-all" />
                  <div className="relative w-11 h-11 bg-linear-to-br from-primary to-primary-600 rounded-xl flex items-center justify-center text-white group-hover:scale-110 transition-transform shadow-md">
                    {benefit.icon}
                  </div>
                </div>
                <span className="bg-primary/10 text-primary px-2.5 py-1 rounded-lg text-xs font-bold border border-primary/20">
                  {benefit.metric}
                </span>
              </div>
              <h3 className="text-base font-bold mb-1.5 text-foreground">
                {benefit.title}
              </h3>
              <p className="text-xs text-foreground/50 leading-relaxed">
                {benefit.description}
              </p>
            </div>
          ))}
        </div>
      </div>

      {/* How It Works */}
      <div className="py-12 px-4">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-8">
            <h2 className="text-2xl md:text-3xl font-bold mb-2">
              {t("pages.enhancedSellerMarketing.how.title")}
            </h2>
            <p className="text-sm text-foreground/50">
              {t("pages.enhancedSellerMarketing.how.subtitle")}
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-6 relative z-20">
            {steps.map((item, idx) => (
              <div key={idx} className="relative">
                <div className="h-full rounded-2xl p-5 border border-divider hover:border-primary-400 transition-all hover:shadow-xl bg-content1 flex flex-col">
                  <div className="absolute -top-4 left-5 w-11 h-11 bg-linear-to-br from-primary-600 to-primary-500 text-white rounded-xl flex items-center justify-center text-lg font-bold shadow-lg border-2 border-content1">
                    {item.step}
                  </div>

                  <div className="mt-6">
                    <div className="relative mb-3 inline-block">
                      <div className="absolute inset-0 bg-primary-400/20 blur-xl rounded-full" />
                      <div className="relative w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary border border-primary/20">
                        {item.icon}
                      </div>
                    </div>
                    <h3 className="font-bold text-base mb-1.5">{item.title}</h3>
                    <p className="text-xs text-foreground/50 leading-relaxed">
                      {item.desc}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Fulfillment Options */}
      <div className="py-12 px-4 hidden">
        <div className="max-w-6xl mx-auto">
          <div className="relative overflow-hidden bg-linear-to-r from-primary-600 via-primary-500 to-primary-600 rounded-2xl p-6 md:p-10 text-white shadow-2xl">
            <div className="absolute top-0 right-0 w-48 h-48 bg-white opacity-5 rounded-full -mr-24 -mt-24" />
            <div className="absolute bottom-0 left-0 w-64 h-64 bg-black opacity-5 rounded-full -ml-32 -mb-32" />

            <div className="relative text-center mb-6">
              <h2 className="text-2xl md:text-3xl font-bold mb-3">
                {t("pages.enhancedSellerMarketing.fulfillment.title")}
              </h2>
              <p className="text-sm text-primary-50 max-w-3xl mx-auto">
                {t("pages.enhancedSellerMarketing.fulfillment.desc")}
              </p>
            </div>

            <div className="grid md:grid-cols-3 gap-3 relative">
              {[
                {
                  icon: <CheckCircle className="w-5 h-5 stroke-2" />,
                  title: t(
                    "pages.enhancedSellerMarketing.fulfillment.items.partnerIntegrations.title"
                  ),
                  desc: t(
                    "pages.enhancedSellerMarketing.fulfillment.items.partnerIntegrations.desc"
                  ),
                },
                {
                  icon: <CheckCircle className="w-5 h-5 stroke-2" />,
                  title: t(
                    "pages.enhancedSellerMarketing.fulfillment.items.realtimeTracking.title"
                  ),
                  desc: t(
                    "pages.enhancedSellerMarketing.fulfillment.items.realtimeTracking.desc"
                  ),
                },
                {
                  icon: <CheckCircle className="w-5 h-5 stroke-2" />,
                  title: t(
                    "pages.enhancedSellerMarketing.fulfillment.items.policies.title"
                  ),
                  desc: t(
                    "pages.enhancedSellerMarketing.fulfillment.items.policies.desc"
                  ),
                },
              ].map((item, idx) => (
                <div
                  key={idx}
                  className="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20 hover:bg-white/15 transition-all"
                >
                  <div className="text-white mb-2">{item.icon}</div>
                  <p className="font-bold mb-0.5 text-sm text-white">
                    {item.title}
                  </p>
                  <p className="text-xs text-primary-100">{item.desc}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Final CTA */}
      <div className="py-12 px-4">
        <div className="max-w-4xl mx-auto">
          <div className="relative overflow-hidden bg-linear-to-r from-primary-600 via-primary-500 to-primary-600 rounded-2xl p-6 md:p-10 text-white text-center shadow-2xl">
            <div className="absolute top-0 right-0 w-48 h-48 bg-white opacity-5 rounded-full -mr-24 -mt-24" />
            <div className="absolute bottom-0 left-0 w-48 h-48 bg-black opacity-5 rounded-full -ml-24 -mb-24" />

            <div className="relative">
              {/* `siteName` is interpolated rather than concatenated so each
                  locale controls the whole sentence, including word order. */}
              <h2 className="text-2xl md:text-3xl font-bold mb-3">
                {t("pages.enhancedSellerMarketing.cta.title", { siteName })}
              </h2>
              <p className="text-sm text-primary-50 mb-6 max-w-2xl mx-auto">
                {t("pages.enhancedSellerMarketing.cta.desc", { siteName })}
              </p>

              {(webSettings?.supportNumber || webSettings?.supportEmail) && (
                <div className="flex flex-col sm:flex-row gap-3 justify-center items-center mb-6">
                  {webSettings?.supportNumber && (
                    <a
                      href={`tel:${webSettings.supportNumber}`}
                      className="flex items-center gap-2 bg-white/10 backdrop-blur-sm px-4 py-2.5 rounded-xl hover:bg-white/15 transition-all text-sm border border-white/20"
                    >
                      <Phone className="w-4 h-4 stroke-2" />
                      <span className="font-medium">
                        {webSettings.supportNumber}
                      </span>
                    </a>
                  )}
                  {webSettings?.supportEmail && (
                    <a
                      href={`mailto:${webSettings.supportEmail}`}
                      className="flex items-center gap-2 bg-white/10 backdrop-blur-sm px-4 py-2.5 rounded-xl hover:bg-white/15 transition-all text-sm border border-white/20"
                    >
                      <Mail className="w-4 h-4 stroke-2" />
                      <span className="font-medium">
                        {webSettings.supportEmail}
                      </span>
                    </a>
                  )}
                </div>
              )}

              <div className="inline-block border-t-2 border-white border-opacity-30 pt-5">
                <h3 className="text-lg font-bold mb-1">
                  {t("pages.enhancedSellerMarketing.cta.registerLabel")}
                </h3>
              </div>
            </div>
          </div>
        </div>
      </div>

      <style>{`
        @keyframes marquee {
          0% {
            transform: translateX(0);
          }
          100% {
            transform: translateX(-33.33%);
          }
        }
        .animate-marquee {
          animation: marquee 20s linear infinite;
        }
      `}</style>
    </div>
  );
}
