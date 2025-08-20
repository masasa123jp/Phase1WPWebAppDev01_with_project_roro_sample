interface Props {
  adUnit: string;
}

export default function AdSlot({ adUnit }: Props) {
  return (
    <div className="border rounded p-4 text-center text-sm text-gray-400">
      {/* Placeholder – replace with Adsense or Ad Manager tags */}
      AD SLOT: {adUnit}
    </div>
  );
}
