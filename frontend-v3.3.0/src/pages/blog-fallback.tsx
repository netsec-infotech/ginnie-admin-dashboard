import type { GetStaticProps } from "next";
import BlogArticlePage from "@/pages/blogs/[slug]";
import { getStaticGenerationContext } from "@/services/staticGenerationService";

// Shared static HTML and JSON shell for articles published after deployment.
export const getStaticProps: GetStaticProps = async () => {
  const { settings } = await getStaticGenerationContext();
  return {
    props: {
      slug: "",
      clientFallback: true,
      initialSettings: settings,
    },
  };
};

export default BlogArticlePage;
