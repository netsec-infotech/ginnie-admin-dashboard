import Image from "next/image";
import { BookOpen } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useState } from "react";
import type { BlogPost } from "@/types/blog";

interface BlogImageProps {
  post: BlogPost;
  className?: string;
  priority?: boolean;
  sizes?: string;
}

export default function BlogImage({
  post,
  className = "",
  priority = false,
  sizes = "(max-width: 768px) 100vw, (max-width: 1023px) 50vw, 33vw",
}: BlogImageProps) {
  const { t } = useTranslation();
  const [failedImage, setFailedImage] = useState<string | null>(null);
  const hasImage = Boolean(post.image && failedImage !== post.image);

  return (
    <div className={`relative overflow-hidden bg-default-100 ${className}`}>
      {hasImage ? (
        <Image
          src={post.image!}
          alt={post.imageAlt || post.title}
          fill
          sizes={sizes}
          preload={priority}
          className="object-cover transition-transform duration-700 group-hover:scale-[1.035] motion-reduce:transition-none"
          onError={() => setFailedImage(post.image)}
        />
      ) : (
        <div
          role="img"
          aria-label={t("blog.imageFallback")}
          className="absolute inset-0 flex items-center justify-center bg-linear-to-br from-primary/10 via-content2 to-primary/20"
        >
          <BookOpen
            className="size-14 text-primary/50"
            strokeWidth={1}
            aria-hidden="true"
          />
        </div>
      )}
    </div>
  );
}
